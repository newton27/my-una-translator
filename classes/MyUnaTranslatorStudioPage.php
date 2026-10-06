<?php defined('BX_DOL') or die('hack attempt');

foreach (['Text','Secret','Provider','LibreProvider','Access','Store','Engine'] as $part)
    require_once __DIR__ . '/MyUnaTranslator' . $part . '.php';

class MyUnaTranslatorStudioPage extends BxTemplStudioModule
{
    private $translator;
    protected $sPageDefault = 'translator';

    public function __construct($sModule, $mixedPageName, $sPage = '')
    {
        parent::__construct($sModule, $mixedPageName, $sPage);
        if ($sPage === '') $this->sPage = 'translator';
        $this->aMenuItems = ['translator'=>['name'=>'translator','icon'=>'language','title'=>'_my_una_translator_title']];
    }
    public function init()
    {
        parent::init();
        bx_import('BxDolSession'); bx_import('BxDolStudioLanguagesUtils');
        $store = new MyUnaTranslatorStore(BxDolDb::getInstance());
        $this->translator = new MyUnaTranslatorEngine($store,
            function ($settings) {
                if (($settings['provider']??'deepl')==='libre') return new MyUnaTranslatorLibreProvider($settings['libre_url'],($settings['libre_key']??'')===''?'':MyUnaTranslatorSecret::open($settings['libre_key'],BX_DOL_SECRET));
                return new MyUnaTranslatorProvider(MyUnaTranslatorSecret::open($settings['api_key'],BX_DOL_SECRET),$settings['plan']);
            },
            function ($code) { return BxDolStudioLanguagesUtils::getInstance()->compileLanguage($code,true); });
    }
    private function token()
    {
        $session=BxDolSession::getInstance();
        $token=(string)$session->getValue('my_una_translator_csrf');
        if (strlen($token)!==64) { $token=bin2hex(random_bytes(32)); $session->setValue('my_una_translator_csrf',$token); }
        return $token;
    }
    public function checkAction()
    {
        $action=$_POST['mt_action']??$_GET['mt_action']??null;
        if ($action===null) return parent::checkAction();
        try {
            MyUnaTranslatorAccess::check($_SERVER['REQUEST_METHOD']??'',function_exists('isAdmin')&&isAdmin(),
                $_POST['csrf_token']??'',(string)BxDolSession::getInstance()->getValue('my_una_translator_csrf'));
            if (empty($this->aModule['enabled'])) throw new RuntimeException('Enable My UNA Translator before using it.');
            if (!is_string($action)) throw new RuntimeException('Invalid Translator action.');
            $input=$_POST; unset($input['csrf_token'],$input['mt_action']);
            $input['operator_id']=getLoggedId();
            return ['code'=>0,'data'=>$this->translator->dispatch($action,$input)];
        } catch (Throwable $e) {
            return ['code'=>1,'message'=>get_class($e)==='RuntimeException' ? $e->getMessage() : 'Translator could not complete the request. Check its installation and try again.'];
        }
    }
    private static function h($value) { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    private static function text($key) { return _t('_my_una_translator_'.$key); }
    private static function label($key) { return self::h(self::text($key)); }

    protected function getTranslator()
    {
        if (!function_exists('isAdmin') || !isAdmin()) return self::label('admin_required');
        $snapshot=$this->translator->snapshot();
        $base=BX_DOL_URL_ROOT.'modules/newton/my_una_translator/';
        $endpoint=BX_DOL_URL_STUDIO.'module.php?name=my_una_translator&page=translator';
        $labels=[];
        foreach (['job','working','saved','quota_used','select_job','job_created','ready','paused','applied','rolled_back','empty_preview','key','language','source','translation','state','no_translation','finish_batch','connection_error','session_error','generating','applying','rolling_back','ceiling_warning','characters','conflicts','used_reserved','staged','total_keys','provider_needed','preview_page','not_started','created','active','disabled','not_created','planned','running','apply_cache_error','rollback_cache_error','pending','processing','conflict','rollback_conflict','show_status','show_progress','translation_status','hide_status','no_job_selected','preview_progress','apply_progress','rollback_progress','seconds','slow_request','waiting_deepl','waiting_apply','waiting_rollback','waiting_server','keep_page_open'] as $key) $labels[$key]=self::text($key);
        foreach (['waiting_libre','libre_available'] as $key) $labels[$key]=self::text($key);
        $config=['endpoint'=>$endpoint,'token'=>$this->token(),'snapshot'=>$snapshot,'labels'=>$labels];
        $html='<link rel="stylesheet" href="'.self::h($base.'template/css/translator.css?v=0.1.8').'">';
        $html.='<section id="my-una-translator" class="mut" aria-label="'.self::label('title').'">';
        $html.='<header class="mut-hero"><div class="mut-mark" aria-hidden="true">A<span>文</span></div><div><div class="mut-brand">My UNA World</div><h2>'.self::label('title').'</h2><p>'.self::label('intro').'</p></div><a class="mut-link" href="'.self::h(BX_DOL_URL_STUDIO.'polyglot.php').'">'.self::label('open_polyglot').'</a></header>';
        $html.='<div id="mut-message" class="mut-message" role="status" aria-live="polite" hidden></div>';
        $html.='<div class="mut-status-launch"><button id="mut-show-status" type="button" class="mut-status-control">'.self::label('show_status').'</button></div>';
        $html.='<dialog id="mut-status-dialog" class="mut-status-dialog" aria-labelledby="mut-status-title"><div class="mut-status-heading"><div><div class="mut-brand">My UNA World</div><h3 id="mut-status-title">'.self::label('translation_status').'</h3></div><button id="mut-hide-status" type="button" class="mut-secondary mut-status-control" autofocus>'.self::label('hide_status').'</button></div>';
        $html.='<p id="mut-status-job" class="mut-note"></p><div id="mut-status-state" class="mut-status-state" role="status" aria-live="polite"></div><p id="mut-status-count" class="mut-status-count"></p><progress id="mut-status-progress" value="0" max="100" aria-label="'.self::label('job_progress').'"></progress>';
        $html.='<p id="mut-status-totals" class="mut-note"></p><p id="mut-status-characters" class="mut-note"></p><p id="mut-status-activity" class="mut-status-activity" hidden></p><p id="mut-status-error" class="mut-warning" role="alert" hidden></p><p id="mut-status-note" class="mut-note"></p><div class="mut-buttons"><button id="mut-status-pause" type="button" class="mut-status-control" data-mut-action="pause" hidden>'.self::label('pause').'</button></div><p class="mut-note">'.self::label('keep_page_open').'</p></dialog>';
        $html.='<details class="mut-card"'.($snapshot['settings']['provider_ready']?'':' open').'><summary>'.self::label('provider_settings').'</summary><form id="mut-settings" class="mut-fields">';
        $html.='<label>'.self::label('provider').'<select name="provider"><option value="libre"'.($snapshot['settings']['provider']==='libre'?' selected':'').'>LibreTranslate</option><option value="deepl"'.($snapshot['settings']['provider']==='deepl'?' selected':'').'>DeepL</option></select></label>';
        $html.='<label data-mut-provider="libre">'.self::label('libre_url').'<input type="url" name="libre_url" value="'.self::h($snapshot['settings']['libre_url']??'').'" maxlength="500" placeholder="https://translate.example.org"></label>';
        $html.='<label data-mut-provider="libre">'.self::label('libre_key').'<input type="password" name="libre_key" autocomplete="new-password" value="" maxlength="512" placeholder="'.self::label($snapshot['settings']['has_libre_key']?'key_saved':'libre_optional_key').'"></label>';
        $html.='<label data-mut-provider="libre"><input type="checkbox" name="clear_libre_key" value="1"> '.self::label('clear_libre_key').'</label>';
        $html.='<label data-mut-provider="deepl">'.self::label('api_account').'<select name="plan"><option value="free"'.($snapshot['settings']['plan']==='free'?' selected':'').'>DeepL free endpoint</option><option value="pro"'.($snapshot['settings']['plan']==='pro'?' selected':'').'>DeepL paid endpoint</option></select></label>';
        $html.='<label data-mut-provider="deepl">'.self::label('api_key').'<input type="password" name="api_key" autocomplete="new-password" value="" placeholder="'.self::label($snapshot['settings']['has_key']?'key_saved':'key_needed').'" maxlength="512"></label>';
        $html.='<label>'.self::label('batch_size').'<input type="number" name="batch_size" min="1" max="20" value="'.(int)$snapshot['settings']['batch_size'].'" required></label>';
        $html.='<label>'.self::label('default_ceiling').'<input type="number" name="character_budget" min="1" max="100000000" value="'.(int)$snapshot['settings']['character_budget'].'" required></label>';
        $html.='<div class="mut-buttons"><button type="submit">'.self::label('save_settings').'</button><button type="button" data-mut-action="usage" class="mut-secondary">'.self::label('check_usage').'</button></div><p class="mut-note">'.self::label('provider_note').'</p></form></details>';
        $html.='<section class="mut-card"><h3>'.self::label('language_coverage').'</h3><div class="mut-table-wrap"><table><thead><tr><th>'.self::label('language').'</th><th>'.self::label('translated').'</th><th>'.self::label('missing').'</th><th>'.self::label('english_copies').'</th><th>'.self::label('availability').'</th></tr></thead><tbody id="mut-coverage"></tbody></table></div></section>';
        $html.='<section class="mut-card"><h3>'.self::label('plan_job').'</h3><form id="mut-create"><div class="mut-targets">';
        foreach (MyUnaTranslatorEngine::presets() as $code=>$p)
            $html.='<label><input type="checkbox" name="targets[]" value="'.self::h($code).'" checked><span>'.self::h($p['native']).'<small>'.self::h($p['name']).'</small></span></label>';
        $html.='</div><div class="mut-fields"><label>'.self::label('category').'<select name="category_id"><option value="0">'.self::label('all_categories').'</option>';
        foreach ($snapshot['categories'] as $c) $html.='<option value="'.(int)$c['id'].'">'.self::h($c['name']).'</option>';
        $html.='</select></label><label>'.self::label('job_ceiling').'<input id="mut-create-budget" type="number" name="character_budget" min="1" max="100000000" value="'.(int)$snapshot['settings']['character_budget'].'" required></label></div>';
        $html.='<label class="mut-check"><input type="checkbox" name="include_english" value="1" checked>'.self::label('include_english').'</label><p class="mut-note">'.self::label('scope_note').'</p><button type="submit">'.self::label('create_job').'</button></form></section>';
        $html.='<section class="mut-card"><h3>'.self::label('jobs_preview').'</h3><label class="mut-job-label">'.self::label('job').'<select id="mut-job-select"></select></label><div id="mut-job-details" class="mut-job-details"></div>';
        $html.='<form id="mut-job-lookup" class="mut-fields"><label>'.self::label('job_id').'<input type="number" name="job_id" min="1" required placeholder="'.self::label('older_job').'"></label><div class="mut-buttons"><button type="submit" class="mut-secondary">'.self::label('open_job').'</button></div></form>';
        $html.='<progress id="mut-progress" value="0" max="100" aria-label="'.self::label('job_progress').'"></progress>';
        $html.='<div class="mut-buttons"><button type="button" data-mut-action="batch">'.self::label('generate_preview').'</button><button type="button" data-mut-action="pause" class="mut-secondary">'.self::label('pause').'</button><button type="button" data-mut-action="resume" class="mut-secondary">'.self::label('resume').'</button></div>';
        $html.='<div class="mut-fields"><label>'.self::label('resume_ceiling').'<input id="mut-resume-budget" type="number" min="1" max="100000000" value="'.(int)$snapshot['settings']['character_budget'].'"></label></div>';
        $html.='<label class="mut-check"><input id="mut-enable-languages" type="checkbox" checked>'.self::label('enable_new_languages').'</label><div class="mut-buttons"><button type="button" data-mut-action="apply">'.self::label('apply_translations').'</button><button type="button" data-mut-action="rollback" class="mut-secondary">'.self::label('rollback').'</button><button type="button" data-mut-action="refresh" class="mut-secondary">'.self::label('refresh').'</button></div>';
        $html.='<p class="mut-note">'.self::label('apply_note').'</p><div id="mut-preview" class="mut-table-wrap"></div><div class="mut-buttons"><button id="mut-prev" class="mut-secondary" type="button">'.self::label('previous').'</button><span id="mut-page-info"></span><button id="mut-next" class="mut-secondary" type="button">'.self::label('next').'</button></div></section>';
        $html.='<footer class="mut-footer">'.self::label('footer_note').'</footer></section>';
        $html.='<script type="application/json" id="mut-config">'.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script><script src="'.self::h($base.'template/js/progress.js?v=0.1.8').'"></script><script src="'.self::h($base.'template/js/translator.js?v=0.1.8-rmjob3').'"></script>';
        return $html;
    }
}
