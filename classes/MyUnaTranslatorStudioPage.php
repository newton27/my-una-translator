<?php defined('BX_DOL') or die('hack attempt');

foreach (['Text','Secret','Provider','LibreProvider','Access','Store','Engine'] as $part)
    require_once __DIR__ . '/MyUnaTranslator' . $part . '.php';

class MyUnaTranslatorStudioPage extends BxTemplStudioModule
{
    private const WORKFLOW_TEXT=[
        'workflow_intro'=>'Choose languages, review the translation preview, then apply it to your site.',
        'step_choose'=>'Choose languages', 'step_review'=>'Generate and review', 'step_apply'=>'Apply to your site',
        'translation_steps'=>'Translation steps', 'next_choose'=>'Choose one to five languages below, then create a plan.',
        'next_generate'=>'Your plan is saved. Generate the preview, then review the translated text before applying.',
        'next_resume'=>'This job is paused. Read its message, adjust the limit if needed, then resume the saved preview.',
        'next_review'=>'Your preview is ready. Review the translated text below, then apply it in step 3.',
        'next_retry_apply'=>'Translations are saved. Retry Apply to finish preparing the language for your site.',
        'next_done'=>'Translations have been applied. Use the site-language links below to check the result.',
        'next_finish_apply'=>'Applying has started. Continue Apply in step 3 to finish this saved job.',
        'next_finish_rollback'=>'Rollback needs to finish. Use the recovery tools in step 3 to continue.',
        'next_empty'=>'There is no untranslated text for this plan. Check the existing site languages below, or choose a different scope.',
        'next_failed'=>'This job could not finish. Read its error before creating another plan.',
        'next_rolled_back'=>'This job has been rolled back. Choose languages in step 1 to start another plan.',
        'default_note'=>'The site default is', 'default_help'=>'Visitors can choose an available language from Language in the site footer. Translating a language does not change the site default.',
        'default_settings'=>'Change the site default in Polyglot (optional)',
        'plan_options'=>'Translation scope and limits', 'plan_note'=>'A plan lists the interface text to translate. Your site changes only when you apply the reviewed preview.',
        'create_continue'=>'Create plan and continue', 'selected_languages'=>'Selected languages', 'no_matches'=>'No matching languages. Try a native name, English name or language code.',
        'review_note'=>'Generate the preview first. Review the source and translation side by side; applying is a separate step.',
        'generate_translations'=>'Generate translation preview', 'resume_preview'=>'Resume this preview',
        'saved_job_tools'=>'Open an older job or adjust its limit', 'apply_publish'=>'Make newly created languages available in the site language selector',
        'apply_help'=>'Apply saves the reviewed text to UNA. The checked option makes new languages available after they are prepared. The site default stays unchanged.',
        'apply_wait'=>'Generate and review a complete preview in step 2 before applying.', 'apply_done'=>'This job is already applied. Check the site-language links below.',
        'apply_continue'=>'Apply reviewed translations', 'recovery_tools'=>'Recovery tools', 'site_languages'=>'Languages on your site',
        'site_languages_help'=>'These languages exist in UNA. Available languages appear in the visitor selector; other translation targets become site languages after you apply their previews.',
        'available_visitors'=>'Available to visitors', 'unavailable_visitors'=>'Hidden from visitors', 'site_default'=>'Site default',
        'edit_translation'=>'Edit translation', 'save_translation'=>'Save translation', 'cancel_edit'=>'Cancel edit',
        'manually_edited'=>'Manually edited', 'preview_saved'=>'Preview saved. Apply the reviewed job when you are ready.',
        'edit_help'=>'Save this correction to the preview. Keep placeholders, links and HTML unchanged. Your site changes only when you apply the job.',
        'job_languages'=>'This job translates', 'no_preview_yet'=>'Your translation preview will appear here after generation starts.',
    ];
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
    private static function text($key) {
        $full='_my_una_translator_'.$key; $text=_t($full);
        $fallback=['languages_intro'=>'Translate your UNA interface into your provider’s supported languages. Preview each job, then apply it to Polyglot.',
            'target_languages'=>'Target languages','find_language'=>'Find a language','target_hint'=>'Choose 1–5 languages for this job. English is the source language.',
            'libre_languages_available'=>'LibreTranslate is available for the supported languages shown above.'];
        return $text===$full ? (self::WORKFLOW_TEXT[$key]??$fallback[$key]??$text) : $text;
    }
    private static function label($key) { return self::h(self::text($key)); }

    protected function getTranslator()
    {
        if (!function_exists('isAdmin') || !isAdmin()) return self::label('admin_required');
        $snapshot=$this->translator->snapshot();
        $base=BX_DOL_URL_ROOT.'modules/newton/my_una_translator/';
        $endpoint=BX_DOL_URL_STUDIO.'module.php?name=my_una_translator&page=translator';
        $labels=[];
        foreach (['job','working','saved','quota_used','select_job','job_created','ready','paused','applied','rolled_back','empty_preview','key','language','source','translation','state','no_translation','finish_batch','connection_error','session_error','generating','applying','rolling_back','ceiling_warning','characters','conflicts','used_reserved','staged','total_keys','provider_needed','preview_page','not_started','created','active','disabled','not_created','planned','running','apply_cache_error','rollback_cache_error','pending','processing','conflict','rollback_conflict','show_status','show_progress','translation_status','hide_status','no_job_selected','preview_progress','apply_progress','rollback_progress','seconds','slow_request','waiting_deepl','waiting_apply','waiting_rollback','waiting_server','keep_page_open'] as $key) $labels[$key]=self::text($key);
        foreach (['waiting_libre','libre_languages_available'] as $key) $labels[$key]=self::text($key);
        $labels['target_hint']=self::text('target_hint');
        foreach(array_keys(self::WORKFLOW_TEXT) as $key) $labels[$key]=self::text($key);
        $default=function_exists('getParam')?(string)getParam('lang_default'):'en';
        $defaultName=$default;
        foreach($snapshot['site_languages'] as $language) if($language['code']===$default) $defaultName=$language['native'];
        $config=['endpoint'=>$endpoint,'token'=>$this->token(),'snapshot'=>$snapshot,'labels'=>$labels,'site_url'=>BX_DOL_URL_ROOT,'default_language'=>$default];
        $html='<link rel="stylesheet" href="'.self::h($base.'template/css/translator.css?v=0.1.11-edit1').'">';
        $html.='<section id="my-una-translator" class="mut" aria-label="'.self::label('title').'">';
        $html.='<header class="mut-hero"><div class="mut-mark" aria-hidden="true">A<span>文</span></div><div><div class="mut-brand">My UNA World</div><h2>'.self::label('title').'</h2><p>'.self::label('workflow_intro').'</p></div></header>';
        // Read-only dashboard: derive everything from the existing snapshot.
        $jobs=$snapshot['jobs']??[];
        $siteLanguages=$snapshot['site_languages']??[];
        $coverage=$snapshot['languages']??[];
        $active=0; $paused=0; $finished=0;
        foreach ($jobs as $job) {
            $state=(string)($job['status']??'');
            if (in_array($state,['planned','running','applying'],true)) ++$active;
            elseif ($state==='paused') ++$paused;
            elseif (in_array($state,['applied','ready'],true)) ++$finished;
        }
        $enabledLanguages=0;
        foreach ($siteLanguages as $language) if (!empty($language['enabled'])) ++$enabledLanguages;
        $missing=0; $copies=0;
        foreach ($coverage as $language) {
            $missing+=(int)($language['missing']??0);
            $copies+=(int)($language['english_copies']??0);
        }
        $latest=$jobs[0]??[];
        $provider=($snapshot['settings']['provider']??'deepl')==='libre'?'LibreTranslate':'DeepL';
        $configured=!empty($snapshot['settings']['provider_ready']);
        $cards=[
            ['Provider',$provider,$configured?'Configured':'Setup needed','provider','#dceefd','#1d5b9d'],
            ['Site languages',(string)count($siteLanguages),$enabledLanguages.' enabled','languages','#dff5ed','#176d53'],
            ['Recent jobs',(string)count($jobs),$active.' active · '.$paused.' paused · '.$finished.' ready/applied','jobs','#eee6fc','#64409d'],
            ['Latest job',empty($latest)?'None':'#'.(int)($latest['id']??0),empty($latest)?'No saved jobs':(string)($latest['status']??'Unknown'),'jobs','#fff0df','#97501b'],
            ['Character budget',empty($latest)?'—':number_format((int)($latest['spent']??0)),empty($latest)?'No recent job':'of '.number_format((int)($latest['budget']??0)).' characters used','jobs','#e6f5f3','#15746c'],
            ['Coverage across languages',number_format($missing).' missing',number_format($copies).' English-copy entries','coverage','#e7edfb','#3e598f'],
        ];
        $html.='<section class="mut-summary-dashboard" aria-label="Translation dashboard"><div class="mut-summary-heading"><h3>Translation overview</h3><p>Coverage totals are summed across target languages · recent jobs limited to 20</p></div><div class="mut-summary-grid">';
        foreach ($cards as $card) {
            $html.='<article class="mut-summary-tile" style="--mut-tile-bg:'.$card[4].';--mut-tile-accent:'.$card[5].'"><span class="mut-summary-label">'.self::h($card[0]).'</span><strong>'.self::h($card[1]).'</strong><span class="mut-summary-note">'.self::h($card[2]).'</span></article>';
        }
        $html.='</div></section>';
        $html.='<nav aria-label="'.self::label('translation_steps').'"><ol class="mut-steps">';
        foreach([1=>'step_choose',2=>'step_review',3=>'step_apply'] as $step=>$key) $html.='<li><a data-mut-step-nav="'.$step.'" href="#mut-step-'.$step.'"><span>'.$step.'</span>'.self::label($key).'</a></li>';
        $html.='</ol></nav><p class="mut-default-summary">'.self::label('default_note').' <strong><bdi>'.self::h($defaultName).'</bdi></strong>. '.self::label('default_help').'</p><div id="mut-next-step" class="mut-next-step" role="status" aria-live="polite">'.self::label('next_choose').'</div>';
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
        $html.='<section id="mut-step-1" class="mut-card mut-step" aria-labelledby="mut-choose-title"><h3 id="mut-choose-title"><span class="mut-step-number">1</span>'.self::label('step_choose').'</h3><form id="mut-create"><p class="mut-note">'.self::label('target_hint').'</p><div class="mut-fields"><label>'.self::label('find_language').'<input id="mut-language-search" type="search" autocomplete="off"></label></div><p id="mut-language-error" class="mut-warning"'.(empty($snapshot['language_error'])?' hidden':'').'>'.self::h($snapshot['language_error']??'').'</p><div id="mut-targets" class="mut-targets" role="group" aria-label="'.self::label('target_languages').'">';
        foreach ($snapshot['languages'] as $p)
            $html.='<label><input type="checkbox" name="targets[]" value="'.self::h($p['code']).'"><span><bdi>'.self::h($p['native']).'</bdi><small>'.self::h($p['name']).'</small></span></label>';
        $html.='</div><p id="mut-no-languages" class="mut-note" hidden>'.self::label('no_matches').'</p><p id="mut-selection-note" class="mut-note" aria-live="polite"></p><details class="mut-advanced"><summary>'.self::label('plan_options').'</summary><div class="mut-fields"><label>'.self::label('category').'<select name="category_id"><option value="0">'.self::label('all_categories').'</option>';
        foreach ($snapshot['categories'] as $c) $html.='<option value="'.(int)$c['id'].'">'.self::h($c['name']).'</option>';
        $html.='</select></label><label>'.self::label('job_ceiling').'<input id="mut-create-budget" type="number" name="character_budget" min="1" max="100000000" value="'.(int)$snapshot['settings']['character_budget'].'" required></label></div>';
        $html.='<label class="mut-check"><input type="checkbox" name="include_english" value="1" checked>'.self::label('include_english').'</label></details><p class="mut-note">'.self::label('plan_note').'</p><button type="submit">'.self::label('create_continue').'</button></form></section>';
        $html.='<section id="mut-step-2" class="mut-card mut-step" aria-labelledby="mut-review-title"><h3 id="mut-review-title"><span class="mut-step-number">2</span>'.self::label('step_review').'</h3><p class="mut-note">'.self::label('review_note').'</p><label class="mut-job-label">'.self::label('job').'<select id="mut-job-select"></select></label><div id="mut-job-details" class="mut-job-details"></div><details class="mut-advanced"><summary>'.self::label('saved_job_tools').'</summary>';
        $html.='<form id="mut-job-lookup" class="mut-fields"><label>'.self::label('job_id').'<input type="number" name="job_id" min="1" required placeholder="'.self::label('older_job').'"></label><div class="mut-buttons"><button type="submit" class="mut-secondary">'.self::label('open_job').'</button></div></form>';
        $html.='<div class="mut-fields"><label>'.self::label('resume_ceiling').'<input id="mut-resume-budget" type="number" min="1" max="100000000" value="'.(int)$snapshot['settings']['character_budget'].'"></label></div>';
        $html.='</details><progress id="mut-progress" value="0" max="100" aria-label="'.self::label('job_progress').'"></progress>';
        $html.='<div class="mut-buttons"><button id="mut-generate" type="button" data-mut-action="batch">'.self::label('generate_translations').'</button><button type="button" data-mut-action="pause" class="mut-secondary" hidden>'.self::label('pause').'</button><button id="mut-resume" type="button" data-mut-action="resume" hidden>'.self::label('resume_preview').'</button><button type="button" data-mut-action="refresh" class="mut-secondary">'.self::label('refresh').'</button></div>';
        $html.='<div id="mut-preview" class="mut-table-wrap"><p class="mut-note">'.self::label('no_preview_yet').'</p></div><div id="mut-preview-pages" class="mut-buttons"><button id="mut-prev" class="mut-secondary" type="button">'.self::label('previous').'</button><span id="mut-page-info"></span><button id="mut-next" class="mut-secondary" type="button">'.self::label('next').'</button></div></section>';
        $html.='<section id="mut-step-3" class="mut-card mut-step" aria-labelledby="mut-apply-title"><h3 id="mut-apply-title"><span class="mut-step-number">3</span>'.self::label('step_apply').'</h3><p id="mut-apply-hint" class="mut-note">'.self::label('apply_wait').'</p><label class="mut-check"><input id="mut-enable-languages" type="checkbox" checked>'.self::label('apply_publish').'</label><p class="mut-note">'.self::label('apply_help').'</p><div class="mut-buttons"><button type="button" data-mut-action="apply">'.self::label('apply_continue').'</button></div><details class="mut-advanced"><summary>'.self::label('recovery_tools').'</summary><button type="button" data-mut-action="rollback" class="mut-secondary">'.self::label('rollback').'</button><p class="mut-note">'.self::label('apply_note').'</p></details></section>';
        $html.='<section class="mut-card" aria-labelledby="mut-site-title"><h3 id="mut-site-title">'.self::label('site_languages').'</h3><p class="mut-note">'.self::label('site_languages_help').'</p><div id="mut-site-languages" class="mut-site-languages"></div><p class="mut-note">'.self::label('default_note').' <strong><bdi>'.self::h($defaultName).'</bdi></strong>. '.self::label('default_help').'</p><a class="mut-settings-link" href="'.self::h(BX_DOL_URL_STUDIO.'polyglot.php').'">'.self::label('default_settings').'</a></section>';
        $html.='<details class="mut-card"><summary>'.self::label('language_coverage').'</summary><div class="mut-table-wrap mut-coverage-wrap"><table><thead><tr><th>'.self::label('language').'</th><th>'.self::label('translated').'</th><th>'.self::label('missing').'</th><th>'.self::label('english_copies').'</th><th>'.self::label('availability').'</th></tr></thead><tbody id="mut-coverage"></tbody></table></div></details>';
        $html.='<footer class="mut-footer">'.self::label('footer_note').'</footer></section>';
        $html.='<script type="application/json" id="mut-config">'.json_encode($config,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE).'</script><script src="'.self::h($base.'template/js/progress.js?v=0.1.8').'"></script><script src="'.self::h($base.'template/js/workflow.js?v=0.1.9-flow1').'"></script><script src="'.self::h($base.'template/js/translator.js?v=0.1.11-edit1').'"></script>';
        return $html;
    }
}
