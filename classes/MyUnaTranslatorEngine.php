<?php defined('BX_DOL') or die('hack attempt');
require_once __DIR__.'/MyUnaTranslatorLanguages.php';

final class MyUnaTranslatorEngine
{
    private $store;
    private $providerFactory;
    private $compile;
    private $languagePresets;

    public static function presets($libreLanguages=null)
    {
        if ($libreLanguages!==null) return MyUnaTranslatorLanguages::fromLibre($libreLanguages);
        return [
            'es'=>['name'=>'Spanish','native'=>'Español','target'=>'ES','flag'=>'es','country'=>'es_ES'],
            'fr'=>['name'=>'French','native'=>'Français','target'=>'FR','flag'=>'fr','country'=>'fr_FR'],
            'de'=>['name'=>'German','native'=>'Deutsch','target'=>'DE','flag'=>'de','country'=>'de_DE'],
            'pt-br'=>['name'=>'Portuguese (Brazil)','native'=>'Português (Brasil)','target'=>'PT-BR','flag'=>'br','country'=>'pt_BR'],
            'zh-cn'=>['name'=>'Chinese (Simplified)','native'=>'简体中文','target'=>'ZH-HANS','flag'=>'cn','country'=>'zh_CN'],
            'ar'=>['name'=>'Arabic','native'=>'العربية','target'=>'AR','flag'=>'sa','country'=>'ar_SA','direction'=>'RTL']
        ];
    }
    public function __construct(MyUnaTranslatorStore $store, $providerFactory, $compile)
    {
        $this->store=$store; $this->providerFactory=$providerFactory; $this->compile=$compile;
    }
    private function provider()
    {
        $settings=$this->store->settings();
        if (($settings['provider']??'deepl')==='deepl' && $settings['api_key']==='') throw new RuntimeException('Save a DeepL API key in Provider settings first.');
        return call_user_func($this->providerFactory,$settings);
    }
    private function id(array $data)
    {
        $id=filter_var($data['job_id']??0,FILTER_VALIDATE_INT);
        if (!$id || $id<1) throw new RuntimeException('Select a valid translation job.');
        return (int)$id;
    }
    private function availablePresets()
    {
        if ($this->languagePresets!==null) return $this->languagePresets;
        $settings=$this->store->settings();
        return $this->languagePresets=($settings['provider']??'deepl')==='libre' ? self::presets($this->provider()->languages()) : self::presets();
    }
    public function snapshot()
    {
        $error='';
        try { $presets=$this->availablePresets(); }
        catch (RuntimeException $e) { $presets=[]; $error=$e->getMessage(); }
        return ['settings'=>$this->store->publicSettings(),'languages'=>$this->store->coverage($presets),'language_error'=>$error,'site_languages'=>$this->store->siteLanguages(),
            'categories'=>$this->store->categories(),'jobs'=>$this->store->jobs()];
    }
    public function dispatch($action,array $data)
    {
        if ($action==='snapshot') return $this->snapshot();
        if ($action==='status') return ['job'=>$this->store->job($this->id($data))];
        if ($action==='preview') return ['job'=>$this->store->job($this->id($data)),'items'=>$this->store->preview($this->id($data),$data['offset']??0)];
        $allowed=['settings','usage','create','batch','pause','resume','apply','rollback','delete_job','edit_item'];
        if (!in_array($action,$allowed,true)) throw new RuntimeException('Unknown Translator action.');
        $this->store->lock();
        try {
            switch ($action) {
                case 'edit_item': return $this->editItem($this->id($data),$data);
                case 'delete_job': $id=$this->id($data); $this->store->deleteJob($id); return ['deleted'=>true,'job_id'=>$id];
            case 'settings': $this->languagePresets=null; return ['settings'=>$this->store->saveSettings($data)];
                case 'usage': return ['usage'=>$this->provider()->usage()];
                case 'create': return $this->create($data);
                case 'batch': return $this->batch($this->id($data));
                case 'pause':
                    $id=$this->id($data); $j=$this->store->job($id);
                    if (!in_array($j['status'],['planned','running','paused'],true)) throw new RuntimeException('This job is not generating translations.');
                    $this->store->changeJob($id,['status'=>'paused']); return ['job'=>$this->store->job($id),'more'=>false];
                case 'resume': return $this->resume($this->id($data),$data);
                case 'apply': return $this->apply($this->id($data),$data);
                case 'rollback': return $this->rollback($this->id($data));
            }
        } finally { $this->store->unlock(); }
    }

    private function editItem($jobId,array $data)
    {
        $job=$this->store->job($jobId);
        if (!in_array($job['status'],['ready','paused'],true))
            throw new RuntimeException('Only ready or paused previews can be edited.');
        $itemId=filter_var($data['item_id']??0,FILTER_VALIDATE_INT);
        if (!$itemId || $itemId<1) throw new RuntimeException('Select a valid preview item.');
        $item=$this->store->item($jobId,$itemId);
        if (!$item || $item['state']!=='staged' || !is_string($item['result']))
            throw new RuntimeException('This item is not an editable preview.');
        $hash=$data['expected_hash']??null;
        if (!is_string($hash) || !hash_equals(hash('sha256',$item['result']),$hash))
            throw new RuntimeException('This preview changed in another window. Cancel and refresh before editing again.');
        $result=$data['result']??null;
        if (!is_string($result) || strlen($result)>1048576)
            throw new RuntimeException('Enter translation text of at most 1 MB.');
        MyUnaTranslatorText::validate($item['source'],$result);
        // Never change source or before_value: Apply must still detect later Polyglot edits.
        $this->store->changeItem($itemId,['result'=>$result,'manually_edited'=>1,'error'=>'']);
        $item=$this->store->previewItem($jobId,$itemId);
        return ['job'=>$this->store->job($jobId),'item'=>$item];
    }

    private function create(array $data)
    {
        $targets=$data['targets']??[];
        if (!is_array($targets) || !$targets || count($targets)>5) throw new RuntimeException('Select one to five target languages.');
        $presets=$this->availablePresets();
        foreach ($targets as $target) if (!is_string($target) || !isset($presets[$target])) throw new RuntimeException('Select supported target languages.');
        $targets=array_values(array_unique($targets));
        $category=filter_var($data['category_id']??0,FILTER_VALIDATE_INT);
        if ($category===false || $category<0 || ($category && !$this->store->one('SELECT ID FROM sys_localization_categories WHERE ID=?',$category)))
            throw new RuntimeException('Select a valid Polyglot category.');
        $budget=filter_var($data['character_budget']??$this->store->settings()['character_budget'],FILTER_VALIDATE_INT);
        if ($budget===false || $budget<1 || $budget>100000000) throw new RuntimeException('Set a character ceiling between 1 and 100,000,000.');
        $include=!empty($data['include_english']); $total=0; $estimated=0;
        $this->store->q('START TRANSACTION');
        try {
            $id=$this->store->create(['targets'=>$targets,'category_id'=>$category,'include_english'=>$include,'budget'=>$budget,'operator_id'=>$data['operator_id']??0]);
            foreach ($targets as $code) {
                $lang=$this->store->language($code); $items=[];
                foreach ($this->store->sources((int)($lang['ID']??0),$category) as $row) {
                    if (trim($row['source'])==='') continue;
                    $before=$row['before_value'];
                    if ($before!==null && trim($before)!=='' && (!$include || $before!==$row['source'])) continue;
                    if (++$total>50000) throw new RuntimeException('This job exceeds 50,000 keys. Choose fewer languages or one Polyglot category.');
                    $packet=MyUnaTranslatorText::encodeFragments($row['source']);
                    $estimated+=$packet['segments'] ? mb_strlen($packet['xml'],'UTF-8') : 0;
                    $row['language']=$code; $items[]=$row;
                    if (count($items)===100) { $this->store->insertItems($id,$items); $items=[]; }
                }
                $this->store->insertItems($id,$items);
            }
            $this->store->changeJob($id,['total'=>$total,'estimated'=>$estimated,'status'=>$total?'planned':'ready']);
            $this->store->q('COMMIT');
        } catch (Throwable $e) { $this->store->q('ROLLBACK'); throw $e; }
        return ['job'=>$this->store->job($id),'more'=>false];
    }

    private function batch($id)
    {
        $job=$this->store->job($id);
        if (!in_array($job['status'],['planned','running'],true)) return ['job'=>$job,'more'=>false];
        if ($job['counts']['processing']) {
            $this->store->changeJob($id,['status'=>'paused','error'=>'An earlier provider batch did not finish. Check the translation service before explicitly resuming; retrying may process the same characters again.']);
            return ['job'=>$this->store->job($id),'more'=>false];
        }
        $pending=$this->store->items($id,['pending'],(int)$this->store->settings()['batch_size']);
        if (!$pending) { $this->store->changeJob($id,['status'=>'ready','error'=>'']); return ['job'=>$this->store->job($id),'more'=>false]; }
        $rows=[]; $estimated=0; $code=$pending[0]['language'];
        foreach ($pending as $row) {
            if ($row['language']!==$code) break;
            $packet=MyUnaTranslatorText::encodeFragments($row['source']);
            $cost=$packet['segments'] ? mb_strlen($packet['xml'],'UTF-8') : 0;
            if ($rows && $estimated+$cost>6000) break;
            if ((int)$job['spent']+$estimated+$cost>(int)$job['budget']) break;
            $rows[]=$row; $estimated+=$cost;
        }
        if (!$rows) {
            $this->store->changeJob($id,['status'=>'paused','error'=>'The job character ceiling would be exceeded. Increase its ceiling and explicitly resume.']);
            return ['job'=>$this->store->job($id),'more'=>false];
        }
        try {
            $provider=$this->provider();
            $target=$this->availablePresets()[$code]['target']??null;
            if (!$target) throw new RuntimeException('This job language is not available from the selected provider. Restore its provider or load the required language model.');
            if ($estimated) {
                $usage=$provider->usage();
                if ($usage['remaining']!==null && $usage['remaining']<$estimated) throw new RuntimeException('The remaining DeepL character allowance is below the next batch estimate.');
            }
            $this->store->q('START TRANSACTION');
            foreach ($rows as $row) $this->store->changeItem($row['id'],['state'=>'processing']);
            $reserved=(int)$job['spent']+$estimated;
            $this->store->changeJob($id,['status'=>'running','spent'=>$reserved,'error'=>'']); $this->store->q('COMMIT');
            $translations=$provider->translate(array_column($rows,'source'),$target);
            $billed=0; $this->store->q('START TRANSACTION');
            foreach ($rows as $i=>$row) {
                $this->store->changeItem($row['id'],['state'=>'staged','result'=>$translations[$i]['text'],'error'=>'']);
                $billed+=(int)$translations[$i]['billed'];
            }
            $this->store->changeJob($id,['spent'=>(int)$job['spent']+$billed]); $this->store->q('COMMIT');
        } catch (Throwable $e) {
            $this->store->q('ROLLBACK');
            $safe=get_class($e)==='RuntimeException' ? $e->getMessage() : 'The translation batch could not be saved. Check provider usage before retrying.';
            $this->store->changeJob($id,['status'=>'paused','error'=>mb_substr($safe,0,1000)]);
            return ['job'=>$this->store->job($id),'more'=>false];
        }
        $job=$this->store->job($id); $more=$job['counts']['pending']>0;
        if (!$more) { $this->store->changeJob($id,['status'=>'ready']); $job=$this->store->job($id); }
        return ['job'=>$job,'more'=>$more];
    }

    private function recheckPreviews($id)
    {
        $after=0; $invalid=0;
        while($rows=$this->store->items($id,['staged'],100,$after)) {
            foreach($rows as $row) {
                $after=(int)$row['id'];
                try { MyUnaTranslatorText::validate($row['source'],$row['result']); }
                catch(RuntimeException $e) {
                    $this->store->changeItem($row['id'],['state'=>'pending','error'=>'An earlier preview changed protected content and will be regenerated.']);
                    ++$invalid;
                }
            }
        }
        return $invalid;
    }

    private function resume($id,array $data)
    {
        $j=$this->store->job($id);
        if (!in_array($j['status'],['planned','running','paused'],true)) throw new RuntimeException('This job cannot resume generation.');
        $budget=filter_var($data['character_budget']??$j['budget'],FILTER_VALIDATE_INT);
        if ($budget===false || $budget<max(1,(int)$j['spent']) || $budget>100000000) throw new RuntimeException('The new ceiling must cover the characters already used or reserved.');
        $this->recheckPreviews($id);
        $this->store->q("UPDATE my_una_translator_items SET state='pending' WHERE job_id=? AND state='processing'",$id);
        $this->store->changeJob($id,['status'=>'running','error'=>'','budget'=>(int)$budget]);
        return ['job'=>$this->store->job($id),'more'=>true];
    }

    private function apply($id,array $data)
    {
        $j=$this->store->job($id);
        if (!in_array($j['status'],['ready','applying','applied','apply_cache_error'],true)) throw new RuntimeException('Finish generating the preview before applying it.');
        if ($j['status']==='ready') {
            if ($this->recheckPreviews($id)) {
                $this->store->changeJob($id,['status'=>'paused','error'=>'Some earlier previews changed protected content. Resume generation to rebuild them before applying.']);
                return ['job'=>$this->store->job($id),'more'=>false];
            }
            $this->store->changeJob($id,['status'=>'applying','enable_languages'=>!empty($data['enable_languages'])?1:0,'error'=>'']);
        }
        $j=$this->store->job($id);
        foreach ($this->store->items($id,['staged','applying'],100) as $item) {
            $source=$this->store->sourceNow($item['key_id']);
            if (!$source || $source['source']!==$item['source'] || $source['key_name']!==$item['key_name']) { $this->store->changeItem($item['id'],['state'=>'conflict','error'=>'English source changed after the preview.']); continue; }
            try { MyUnaTranslatorText::validate($item['source'],$item['result']); }
            catch(RuntimeException $e) { $this->store->changeItem($item['id'],['state'=>'conflict','error'=>'This preview changed protected content and was not applied.']); continue; }
            $langId=$this->store->ensureLanguage($item['language'],MyUnaTranslatorLanguages::preset($item['language']),$id);
            $now=$this->store->valueNow($item['key_id'],$langId);
            if ($item['state']==='applying' && $now===$item['result']) { $this->store->changeItem($item['id'],['state'=>'applied']); continue; }
            if ($now!==$item['before_value']) { $this->store->changeItem($item['id'],['state'=>'conflict','error'=>'Destination text changed after the preview.']); continue; }
            $this->store->changeItem($item['id'],['state'=>'applying']);
            $written=$this->store->applyValue($item,$langId);
            $sourceAfter=$this->store->sourceNow($item['key_id']);
            if ($written || ($item['before_value']===$item['result'] && $sourceAfter && $sourceAfter['source']===$item['source']))
                $this->store->changeItem($item['id'],['state'=>'applied']);
            else $this->store->changeItem($item['id'],['state'=>'conflict','error'=>'Text changed during application; existing text was kept.']);
        }
        $j=$this->store->job($id);
        if ($j['counts']['staged'] || $j['counts']['applying']) return ['job'=>$j,'more'=>true];
        foreach ($this->store->affectedLanguages($id) as $code) {
            if (!call_user_func($this->compile,$code)) {
                $this->store->changeJob($id,['status'=>'apply_cache_error','error'=>'Translations were saved, but the language cache could not be compiled. Check cache directory permissions, then apply again.']);
                return ['job'=>$this->store->job($id),'more'=>false];
            }
        }
        if ($j['enable_languages']) $this->store->enableCreatedLanguages($id);
        $this->store->changeJob($id,['status'=>'applied','error'=>'']);
        return ['job'=>$this->store->job($id),'more'=>false];
    }

    private function rollback($id)
    {
        $j=$this->store->job($id);
        if (!in_array($j['status'],['applied','apply_cache_error','rolling_back','rolled_back','rollback_cache_error'],true))
            throw new RuntimeException('Apply this job before rolling it back.');
        $this->store->changeJob($id,['status'=>'rolling_back','error'=>'']);
        foreach ($this->store->items($id,['applied','rolling_back'],100) as $item) {
            $lang=$this->store->language($item['language']); $langId=(int)($lang['ID']??0);
            $now=$langId ? $this->store->valueNow($item['key_id'],$langId) : null;
            if ($item['state']==='rolling_back' && $now===$item['before_value']) { $this->store->changeItem($item['id'],['state'=>'rolled_back']); continue; }
            if (!$langId || $now!==$item['result']) { $this->store->changeItem($item['id'],['state'=>'rollback_conflict','error'=>'Later destination edits were preserved.']); continue; }
            $this->store->changeItem($item['id'],['state'=>'rolling_back']);
            $restored=$this->store->restoreValue($item,$langId);
            $this->store->changeItem($item['id'], $restored || $item['before_value']===$item['result'] ? ['state'=>'rolled_back'] : ['state'=>'rollback_conflict','error'=>'Destination changed during rollback.']);
        }
        $j=$this->store->job($id);
        if ($j['counts']['applied'] || $j['counts']['rolling_back']) return ['job'=>$j,'more'=>true];
        foreach ($this->store->affectedLanguages($id) as $code) {
            if (!call_user_func($this->compile,$code)) {
                $this->store->changeJob($id,['status'=>'rollback_cache_error','error'=>'Rollback values were saved, but their cache could not be compiled. Check cache permissions and retry rollback.']);
                return ['job'=>$this->store->job($id),'more'=>false];
            }
        }
        $this->store->disableEmptyCreatedLanguages($id);
        $this->store->changeJob($id,['status'=>'rolled_back','error'=>'']);
        return ['job'=>$this->store->job($id),'more'=>false];
    }
}

