<?php defined('BX_DOL') or die('hack attempt');

/** Adds a bounded native Studio editing adapter through documented form hooks. */
class MyUnaTranslatorEditingAlertsResponse extends BxDolAlertsResponse
{
    private const MARKER = 'my_una_translator_editing_session';
    private const REOPEN = 'This English editing session has ended. Reopen the editor before saving.';
    private const CAPTURE_ERROR = 'Your translations could not be protected. Reopen the editor before saving.';
    private const FORMS = [
        'adm-bp-block-edit' => ['table'=>'sys_pages_blocks','action'=>'block_edit','fields'=>['title']],
        'adm-bp-page-create' => ['table'=>'sys_objects_page','action'=>'page_create','fields'=>['title','title_system']],
        'adm-bp-page-edit' => ['table'=>'sys_objects_page','action'=>'page_edit','fields'=>['title','title_system']],
    ];
    private $editing;

    public function __construct($editing = null)
    {
        parent::__construct();
        $this->editing = $editing;
    }

    private function editing()
    {
        if ($this->editing === null) {
            require_once __DIR__.'/MyUnaTranslatorStore.php';
            require_once __DIR__.'/MyUnaTranslatorEditing.php';
            $this->editing = new MyUnaTranslatorEditing(new MyUnaTranslatorStore(BxDolDb::getInstance()));
        }
        return $this->editing;
    }

    public function response($alert)
    {
        if (($alert->sUnit??'') !== 'system' || !in_array($alert->sAction??'', ['form_output','form_check'], true) ||
            !function_exists('isAdmin') || !isAdmin()) return;
        $form = $alert->aExtras['object']??null;
        if (!$this->supportedForm($form)) return;

        if ($alert->sAction === 'form_output') {
            // Native validation redisplays the submitted values in replacement
            // popup HTML. Preserve their session binding even after Finish so a
            // second save cannot submit old placeholders as an unmarked form.
            if ($form->isSubmitted()) {
                $marker = BxDolForm::getSubmittedValue(self::MARKER, $form->aFormAttrs['method']);
                if ($marker !== false) {
                    $form->aInputs[self::MARKER] = ['type'=>'hidden','name'=>self::MARKER,
                        'value'=>is_string($marker) && preg_match('/\A[1-9][0-9]*\z/D', $marker) ? $marker : 'invalid'];
                }
            }
            try {
                $state = $this->editing()->getActiveState();
                if (!$state || ($state['status']??'') !== 'active') return;
                $config = $this->configuration($form, $state);
                $json = json_encode($config, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);
                $asset = BX_DOL_URL_ROOT.'modules/newton/my_una_translator/template/js/editing-form.js?v=editing-3';
                $form->getTemplate()->addJs([$asset]);
                // Native AJAX popups arrive after the page asset list was rendered.
                // Deliver the adapter with this fragment; repeated loads only rescan.
                $alert->aExtras['include'] = ($alert->aExtras['include']??'').'<script type="application/json" class="my-una-translator-editing-config">'.$json.'</script><script src="'.htmlspecialchars($asset, ENT_QUOTES, 'UTF-8').'"></script>';
            } catch (Throwable $error) {
                // Leave the original editor available. A marked/active session
                // must still pass the preservation gate before any native save.
            }
            return;
        }

        // Never recover, replay, or otherwise change a failed native check.
        // In particular, the native CSRF token may already have been consumed.
        if (!$form->isSubmitted() || !$form->isValid()) return;
        try {
            $state = $this->editing()->getActiveState();
            $marker = BxDolForm::getSubmittedValue(self::MARKER, $form->aFormAttrs['method']);
            if (!$state && $marker === false) return;
            if (!$state || ($state['status']??'') !== 'active' || !is_string($marker) ||
                !preg_match('/\A[1-9][0-9]*\z/D', $marker) || $marker !== (string)$state['id']) {
                throw new RuntimeException(self::REOPEN);
            }
            $config = $this->configuration($form, $state);
            // Resolve database-backed native targets only while the same lock
            // excludes Finish and other Translator requests until shutdown.
            if ($this->editing()->lockForNativeSave((int)$state['id']) !== true) {
                throw new RuntimeException(self::CAPTURE_ERROR);
            }
            $records = self::expectedTranslations($form, $config['languages']);
            // The manager rechecks the session under its advisory lock and
            // retains that lock through the native write until request shutdown.
            if ($this->editing()->captureExpected((int)$state['id'], $records) !== true) {
                throw new RuntimeException(self::CAPTURE_ERROR);
            }
        } catch (Throwable $error) {
            $this->reject($form, $error instanceof RuntimeException ? $error->getMessage() : self::CAPTURE_ERROR);
        }
    }

    private function supportedForm($form)
    {
        if (!$form instanceof BxDolStudioForm) return false;
        $id = $form->aFormAttrs['id']??'';
        if (!isset(self::FORMS[$id]) || $form->getId() !== $id || ($form->aFormAttrs['method']??'') !== 'post') return false;
        $expected = self::FORMS[$id];
        if (($form->aParams['db']['table']??'') !== $expected['table'] || ($form->aParams['db']['key']??'') !== 'id' ||
            ($form->aParams['db']['submit_name']??'') !== 'do_submit' || !empty($form->aParams['view_mode']) ||
            ($form->aParams['csrf']['disable']??false) === true) return false;
        $action = $form->aFormAttrs['action']??'';
        if (!is_string($action) || basename((string)parse_url($action, PHP_URL_PATH)) !== 'builder_page.php') return false;
        parse_str((string)parse_url($action, PHP_URL_QUERY), $query);
        if (($query['bp_action']??'') !== $expected['action']) return false;
        if ($id === 'adm-bp-block-edit') {
            $input = $form->aInputs['title']??[];
            return ($input['type']??'') === 'text_translatable' && ($input['name']??'') === 'title' &&
                strtolower((string)($input['checker']['func']??'')) === 'lengthtranslatable' &&
                ($input['checker']['params']??[]) === [3,100,'title'];
        }
        // The page builder merges title definitions only after form_check. Its
        // original settings controls are nested native HTML at this point.
        return ($form->aInputs['settings']['type']??'') === 'custom';
    }

    private function configuration($form, array $state)
    {
        // Restoration flags describe the state before Pause. Rendering needs
        // current installed languages, including the still-enabled source.
        $formLanguages = $state['form_languages']??($state['languages']??null);
        if (empty($state['id']) || !is_string($state['default']??null) || !is_array($formLanguages)) {
            throw new RuntimeException(self::CAPTURE_ERROR);
        }
        $languages = []; $seen = []; $sourceFound = false;
        foreach ($formLanguages as $language) {
            $code = $language['code']??null;
            if (!is_string($code) || !preg_match('/\A[a-zA-Z0-9_-]{1,32}\z/D', $code) || isset($seen[$code]) ||
                !in_array($language['enabled']??null, [0,1,'0','1',false,true], true)) {
                throw new RuntimeException(self::CAPTURE_ERROR);
            }
            $seen[$code] = true;
            $enabled = (int)(bool)$language['enabled'];
            $languages[] = ['id'=>(int)($language['id']??0),'code'=>$code,'enabled'=>$enabled];
            if ($code === $state['default'] && $enabled) $sourceFound = true;
        }
        if (!$sourceFound) throw new RuntimeException(self::CAPTURE_ERROR);
        return ['form_id'=>$form->getId(),'session_id'=>(int)$state['id'],'source'=>$state['default'],
            'languages'=>$languages,'fields'=>self::FORMS[$form->getId()]['fields'],
            'length_mode'=>function_exists('mb_strlen') ? 'unicode' : 'bytes'];
    }

    /** Extract actual native values; never modify submitted data or validators. */
    public static function expectedTranslations($form, array $languages)
    {
        $fields = [];
        if ($form->getId() === 'adm-bp-block-edit') {
            foreach (['title','description','content_empty','help','content'] as $name) {
                $input = $form->aInputs[$name]??[];
                if (($input['name']??'') === $name && in_array($input['type']??'', ['text_translatable','textarea_translatable'], true)) {
                    $fields[] = $name;
                }
            }
        } else {
            $fields = ['title','title_system','cover_title'];
        }

        $records = []; $method = $form->aFormAttrs['method'];
        foreach ($fields as $name) {
            $rawKey = BxDolForm::getSubmittedValue($name, $method);
            if (!is_string($rawKey) || preg_match('//u', $rawKey) !== 1) throw new RuntimeException(self::CAPTURE_ERROR);
            $key = isset($form->aInputs[$name]) ? $form->getCleanValue($name) : BxDolFormCheckerHelper::passXss($rawKey);
            if (!is_string($key)) throw new RuntimeException(self::CAPTURE_ERROR);
            $values = []; $allEmpty = true;
            foreach ($languages as $language) {
                $value = BxDolForm::getSubmittedValue($name.'-'.$language['code'], $method);
                if (!is_string($value) || preg_match('//u', $value) !== 1) throw new RuntimeException(self::CAPTURE_ERROR);
                if (!empty($value)) $allEmpty = false;
                // Studio's language utility applies native text input processing
                // before writing translations, including HTML translation text.
                $values[$language['code']] = bx_process_input(bx_process_input($value));
            }

            $languageBody = $name === 'content' && $form->getId() === 'adm-bp-block-edit';
            $bodyIntermediate = $key;
            if ($languageBody) $key = self::languageBodyKey();
            // Other brand-new key names are generated later by UNA. The
            // session's persisted source comparison discovers those new keys.
            if ($key === '') continue;
            foreach ($values as $code => $value) {
                if ($languageBody) $records[] = ['key_name'=>$key,'language'=>$code,'value'=>bx_process_input($bodyIntermediate)];
                // UNA deletes the whole key when every submitted value is
                // empty. The manager rejects this if meaningful originals exist.
                $records[] = ['key_name'=>$key,'language'=>$code,'value'=>$allEmpty ? null : $value];
            }
        }
        return $records;
    }

    private static function languageBodyKey()
    {
        // onSaveBlockLang chooses its write target from the stored block and
        // uses the submitted hidden content only as the intermediate value.
        // initChecker has already replaced aInputs values with submitted data.
        $id = bx_get('id');
        if (!is_string($id) || !ctype_digit($id) || (int)$id < 1) throw new RuntimeException(self::CAPTURE_ERROR);
        $db = BxDolDb::getInstance();
        $block = $db->getRow($db->prepare('SELECT id,type,content FROM sys_pages_blocks WHERE id=?', (int)$id));
        if (!$block || (int)($block['id']??0) !== (int)$id || ($block['type']??'') !== 'lang' ||
            !is_string($block['content']??null) || preg_match('//u', $block['content']) !== 1) {
            throw new RuntimeException(self::CAPTURE_ERROR);
        }
        return $block['content'] !== '' ? $block['content'] : '_sys_bpb_content_'.(int)$block['id'];
    }

    private function reject($form, $message)
    {
        $form->setValid(false);
        $errorField = isset($form->aInputs['settings']) ? 'settings' : 'title';
        foreach ($form->aInputs as $name => $input) {
            if (($input['type']??'') === 'submit') { $errorField = $name; break; }
            if (($input['type']??'') !== 'input_set') continue;
            foreach ($input as $child) {
                if (is_array($child) && ($child['type']??'') === 'submit') { $errorField = $name; break 2; }
            }
        }
        $existing = $form->aInputs[$errorField]['error']??'';
        $form->aInputs[$errorField]['error'] = $existing === '' ? $message : $existing.' '.$message;
    }
}
