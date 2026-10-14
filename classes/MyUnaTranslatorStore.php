<?php defined('BX_DOL') or die('hack attempt');

/** Uses UNA's existing database connection, including its advisory locks. */
final class MyUnaTranslatorStore
{
    private $db;
    public function __construct($db) { $this->db = $db; }
    private function sql($sql, array $args) { return $this->db->prepare($sql, ...$args); }
    public function q($sql, ...$args) { $r = $this->db->query($this->sql($sql, $args)); if ($r === false) throw new RuntimeException('A database operation failed.'); return $r; }
    public function one($sql, ...$args) { return $this->db->getOne($this->sql($sql, $args)); }
    public function row($sql, ...$args) { return $this->db->getRow($this->sql($sql, $args)) ?: []; }
    public function all($sql, ...$args) { return $this->db->getAll($this->sql($sql, $args)) ?: []; }
    public function lastId() { return (int)$this->db->lastId(); }
    public function lockName() { return 'my_una_translator:' . substr(hash('sha256', (string)$this->one('SELECT DATABASE()')), 0, 32); }
    public function lock() { if ((int)$this->one('SELECT GET_LOCK(?,0)', $this->lockName()) !== 1) throw new RuntimeException('Another translation request is active. Wait for it to finish.'); }
    public function unlock() { $this->one('SELECT RELEASE_LOCK(?)', $this->lockName()); }

    public function settings()
    {
        $r = $this->row('SELECT * FROM my_una_translator_settings WHERE id=1');
        if (!$r) throw new RuntimeException('Translator settings are missing. Check the native installation.');
        return $r;
    }
    public function publicSettings()
    {
        $r = $this->settings(); $r['provider']=$r['provider']??'deepl';
        $r['has_key'] = $r['api_key'] !== ''; $r['has_libre_key']=($r['libre_key']??'')!=='';
        $r['provider_ready']=$r['provider']==='libre' ? ($r['libre_url']??'')!=='' : $r['has_key'];
        unset($r['api_key'],$r['libre_key']); return $r;
    }
    public function saveSettings(array $input)
    {
        $old = $this->settings();
        $provider=$input['provider']??$old['provider']??'deepl';
        if (!in_array($provider,['deepl','libre'],true)) throw new RuntimeException('Select DeepL or LibreTranslate.');
        $libreUrl=$input['libre_url']??$old['libre_url']??'';
        if (!is_string($libreUrl)) throw new RuntimeException('The LibreTranslate service URL is invalid.');
        $libreUrl=trim($libreUrl);
        if ($libreUrl!=='') $libreUrl=MyUnaTranslatorLibreProvider::endpoint($libreUrl);
        if ($provider==='libre' && $libreUrl==='') throw new RuntimeException('Enter the verified LibreTranslate service URL first.');
        $plan = $input['plan'] ?? $old['plan'];
        $batch = filter_var($input['batch_size'] ?? $old['batch_size'], FILTER_VALIDATE_INT);
        $budget = filter_var($input['character_budget'] ?? $old['character_budget'], FILTER_VALIDATE_INT);
        if (!in_array($plan, ['free','pro'], true) || $batch < 1 || $batch > 20 || $budget < 1 || $budget > 100000000)
            throw new RuntimeException('Use a Free/Pro account, 1–20 keys per batch, and a character ceiling of 1–100,000,000.');
        $sealed = $old['api_key'];
        if (isset($input['api_key']) && trim((string)$input['api_key']) !== '') {
            $key = trim((string)$input['api_key']);
            if (strlen($key) > 512 || preg_match('/[\r\n]/', $key)) throw new RuntimeException('The API key is invalid.');
            $sealed = MyUnaTranslatorSecret::seal($key, defined('BX_DOL_SECRET') ? BX_DOL_SECRET : '');
        }
        $libreKey=$libreUrl!==($old['libre_url']??'') || !empty($input['clear_libre_key']) ? '' : ($old['libre_key']??'');
        if (isset($input['libre_key']) && trim((string)$input['libre_key'])!=='') {
            $key=trim((string)$input['libre_key']);
            if (strlen($key)>512 || preg_match('/[\r\n\x00]/',$key)) throw new RuntimeException('The LibreTranslate API key is invalid.');
            $libreKey=MyUnaTranslatorSecret::seal($key,defined('BX_DOL_SECRET')?BX_DOL_SECRET:'');
        }
        $this->q('UPDATE my_una_translator_settings SET plan=?,api_key=?,batch_size=?,character_budget=?,provider=?,libre_url=?,libre_key=? WHERE id=1', $plan,$sealed,(int)$batch,(int)$budget,$provider,$libreUrl,$libreKey);
        return $this->publicSettings();
    }

    public function englishId()
    {
        $id = (int)$this->one("SELECT ID FROM sys_localization_languages WHERE Name='en'");
        if (!$id) throw new RuntimeException('Install the UNA English language before using Translator.');
        return $id;
    }
    public function language($code) { return $this->row('SELECT * FROM sys_localization_languages WHERE Name=?', $code); }
    public function categories() { return $this->all('SELECT ID AS id,Name AS name FROM sys_localization_categories ORDER BY Name'); }
    public function siteLanguages()
    {
        $rows=$this->all('SELECT Name AS code,Title AS native,Enabled AS enabled FROM sys_localization_languages ORDER BY ID');
        foreach ($rows as &$row) $row['enabled']=(bool)$row['enabled'];
        return $rows;
    }
    public function sources($destinationId, $category)
    {
        return $this->all('SELECT k.ID AS key_id,k.`Key` AS key_name,e.String AS source,t.String AS before_value FROM sys_localization_keys k JOIN sys_localization_strings e ON e.IDKey=k.ID AND e.IDLanguage=? LEFT JOIN sys_localization_strings t ON t.IDKey=k.ID AND t.IDLanguage=? WHERE (?=0 OR k.IDCategory=?) ORDER BY k.ID', $this->englishId(),$destinationId,$category,$category);
    }
    public function coverage(array $presets)
    {
        $result = []; $englishId=$presets ? $this->englishId() : 0; $sourceTotal=null;
        foreach ($presets as $code => $preset) {
            $lang = $this->language($code); $id = (int)($lang['ID'] ?? 0);
            if (!$id) {
                if ($sourceTotal===null) $sourceTotal=(int)$this->one("SELECT COUNT(*) FROM sys_localization_strings WHERE IDLanguage=? AND String<>''",$englishId);
                $c=['total'=>$sourceTotal,'missing'=>$sourceTotal,'english_copies'=>0];
            } else $c = $this->row("SELECT COUNT(*) AS total,COALESCE(SUM(t.IDKey IS NULL OR t.String=''),0) AS missing,COALESCE(SUM(t.IDKey IS NOT NULL AND t.String<>'' AND BINARY t.String=BINARY e.String),0) AS english_copies FROM sys_localization_strings e LEFT JOIN sys_localization_strings t ON t.IDKey=e.IDKey AND t.IDLanguage=? WHERE e.IDLanguage=? AND e.String<>''", $id,$englishId);
            $result[] = array_merge($preset,['code'=>$code,'installed'=>$id>0,'enabled'=>!empty($lang['Enabled']),
                'total'=>(int)$c['total'],'missing'=>(int)$c['missing'],'english_copies'=>(int)$c['english_copies'],
                'translated'=>(int)$c['total']-(int)$c['missing']-(int)$c['english_copies']]);
        }
        return $result;
    }

    public function create(array $data)
    {
        $this->q('INSERT INTO my_una_translator_jobs (created_at,operator_id,targets,category_id,include_english,budget) VALUES (?,?,?,?,?,?)', time(),(int)($data['operator_id']??0),json_encode($data['targets']),(int)$data['category_id'],(int)$data['include_english'],(int)$data['budget']);
        return $this->lastId();
    }
    public function insertItems($jobId, array $items)
    {
        if (!$items) return;
        $values=[]; $args=[];
        foreach ($items as $r) { $values[]='(?,?,?,?,?,?)'; array_push($args,$jobId,$r['key_id'],$r['key_name'],$r['language'],$r['source'],$r['before_value']); }
        $this->q('INSERT INTO my_una_translator_items (job_id,key_id,key_name,language,source,before_value) VALUES '.implode(',',$values), ...$args);
    }
    public function job($id)
    {
        $r = $this->row('SELECT * FROM my_una_translator_jobs WHERE id=?',(int)$id);
        if (!$r) throw new RuntimeException('Translation job was not found.');
        $r['targets']=json_decode($r['targets'],true); $r['counts']=array_fill_keys(['pending','processing','staged','applying','applied','rolled_back','conflict','rollback_conflict','rolling_back'],0);
        foreach ($this->all('SELECT state,COUNT(*) AS n FROM my_una_translator_items WHERE job_id=? GROUP BY state',(int)$id) as $c) $r['counts'][$c['state']] = (int)$c['n'];
        $r['counts']['unchanged']=$this->unchangedCount($id);
        $r['counts']['translated']=max(0,(int)$r['counts']['staged']-(int)$r['counts']['unchanged']);
        return $r;
    }
    public function jobs() { return $this->all('SELECT id,created_at,status,targets,total,estimated,budget,spent,error FROM my_una_translator_jobs ORDER BY id DESC LIMIT 20'); }
    public function deleteJob($id)
    {
        $id=(int)$id;
        $job=$this->job($id);
        if (!in_array($job['status'],['paused','applied','rolled_back','failed','empty_preview'],true)) throw new RuntimeException('Only paused, completed or failed jobs can be removed.');
        $this->q('START TRANSACTION');
        try {
            $this->q('DELETE FROM my_una_translator_items WHERE job_id=?',$id);
            $this->q('DELETE FROM my_una_translator_jobs WHERE id=?',$id);
            $this->q('COMMIT');
        } catch (Throwable $e) {
            $this->q('ROLLBACK');
            throw $e;
        }
    }
    public function changeJob($id, array $fields)
    {
        $allowed=['status','error','spent','total','estimated','budget','enable_languages']; $sets=[]; $args=[];
        foreach ($fields as $key=>$value) { if (!in_array($key,$allowed,true)) throw new RuntimeException('Invalid job update.'); $sets[]='`'.$key.'`=?'; $args[]=$value; }
        $args[]=(int)$id; $this->q('UPDATE my_una_translator_jobs SET '.implode(',',$sets).' WHERE id=?', ...$args);
    }
    public function items($id, array $states, $limit=20, $afterId=0)
    {
        return $this->all('SELECT * FROM my_una_translator_items WHERE job_id=? AND id>? AND state IN ('.implode(',',array_fill(0,count($states),'?')).') ORDER BY id LIMIT '.max(1,min(200,(int)$limit)), (int)$id,max(0,(int)$afterId),...$states);
    }
    public function changeItem($id, array $fields)
    {
        $sets=[]; $args=[];
        foreach ($fields as $key=>$value) { if (!in_array($key,['state','result','error'],true)) throw new RuntimeException('Invalid item update.'); $sets[]='`'.$key.'`=?'; $args[]=$value; }
        $args[]=(int)$id; $this->q('UPDATE my_una_translator_items SET '.implode(',',$sets).' WHERE id=?', ...$args);
    }
    public function previewItem($jobId,$itemId)
    {
        return $this->row('SELECT * FROM my_una_translator_items WHERE job_id=? AND id=?',(int)$jobId,(int)$itemId);
    }
    public function unchangedCount($jobId)
    {
        return (int)$this->one("SELECT COUNT(*) FROM my_una_translator_items WHERE job_id=? AND state='staged' AND BINARY result=BINARY source",(int)$jobId);
    }
    public function unchangedItems($jobId,$limit=200)
    {
        return $this->all("SELECT id FROM my_una_translator_items WHERE job_id=? AND state='staged' AND BINARY result=BINARY source ORDER BY id LIMIT ".max(1,min(200,(int)$limit)),(int)$jobId);
    }
    public function preview($id,$offset=0)
    {
        $this->job($id);
        return $this->all('SELECT id,key_name,language,source,before_value,result,state,error FROM my_una_translator_items WHERE job_id=? ORDER BY id LIMIT 50 OFFSET '.max(0,(int)$offset), (int)$id);
    }

    public function sourceNow($keyId) { return $this->row('SELECT k.`Key` AS key_name,e.String AS source FROM sys_localization_keys k JOIN sys_localization_strings e ON e.IDKey=k.ID AND e.IDLanguage=? WHERE k.ID=?',$this->englishId(),(int)$keyId); }
    public function valueNow($keyId,$languageId) { $r=$this->row('SELECT String AS value FROM sys_localization_strings WHERE IDKey=? AND IDLanguage=?',(int)$keyId,(int)$languageId); return $r ? $r['value'] : null; }
    public function ensureLanguage($code, array $preset, $jobId)
    {
        $lang=$this->language($code); if ($lang) return (int)$lang['ID'];
        $direction=($preset['direction']??'LTR')==='RTL'?'RTL':'LTR';
        $this->q("INSERT INTO sys_localization_languages (Name,Flag,Title,Direction,LanguageCountry,Enabled) VALUES (?,?,?,?,?,0)",$code,$preset['flag'],$preset['native'],$direction,$preset['country']);
        $id=$this->lastId(); $this->q('INSERT INTO my_una_translator_languages (language,language_id,job_id) VALUES (?,?,?)',$code,$id,(int)$jobId); return $id;
    }
    public function applyValue(array $item,$langId)
    {
        if ($item['before_value'] === null) {
            return $this->q('INSERT INTO sys_localization_strings (IDKey,IDLanguage,String) SELECT e.IDKey,?,? FROM sys_localization_strings e JOIN sys_localization_keys k ON k.ID=e.IDKey WHERE e.IDKey=? AND e.IDLanguage=? AND BINARY e.String=BINARY ? AND BINARY k.`Key`=BINARY ? AND NOT EXISTS (SELECT 1 FROM sys_localization_strings t WHERE t.IDKey=e.IDKey AND t.IDLanguage=?)',(int)$langId,$item['result'],(int)$item['key_id'],$this->englishId(),$item['source'],$item['key_name'],(int)$langId);
        }
        return $this->q('UPDATE sys_localization_strings t JOIN sys_localization_strings e ON e.IDKey=t.IDKey AND e.IDLanguage=? JOIN sys_localization_keys k ON k.ID=t.IDKey SET t.String=? WHERE t.IDKey=? AND t.IDLanguage=? AND BINARY t.String=BINARY ? AND BINARY e.String=BINARY ? AND BINARY k.`Key`=BINARY ?',$this->englishId(),$item['result'],(int)$item['key_id'],(int)$langId,$item['before_value'],$item['source'],$item['key_name']);
    }
    public function restoreValue(array $item,$langId)
    {
        if ($item['before_value'] === null) return $this->q('DELETE FROM sys_localization_strings WHERE IDKey=? AND IDLanguage=? AND BINARY String=BINARY ?',(int)$item['key_id'],(int)$langId,$item['result']);
        return $this->q('UPDATE sys_localization_strings SET String=? WHERE IDKey=? AND IDLanguage=? AND BINARY String=BINARY ?',$item['before_value'],(int)$item['key_id'],(int)$langId,$item['result']);
    }
    public function affectedLanguages($jobId)
    {
        return array_column($this->all("SELECT DISTINCT language FROM my_una_translator_items WHERE job_id=? AND state IN ('applied','rolled_back','rollback_conflict')",(int)$jobId),'language');
    }
    public function enableCreatedLanguages($jobId)
    {
        $this->q('UPDATE sys_localization_languages l JOIN my_una_translator_languages m ON m.language_id=l.ID AND BINARY m.language=BINARY l.Name SET l.Enabled=1 WHERE m.job_id=? AND EXISTS (SELECT 1 FROM my_una_translator_items i WHERE i.job_id=? AND BINARY i.language=BINARY l.Name AND i.state=\'applied\')',(int)$jobId,(int)$jobId);
    }
    public function disableEmptyCreatedLanguages($jobId)
    {
        $this->q('UPDATE sys_localization_languages l JOIN my_una_translator_languages m ON m.language_id=l.ID AND BINARY m.language=BINARY l.Name SET l.Enabled=0 WHERE m.job_id=? AND NOT EXISTS (SELECT 1 FROM sys_localization_strings s WHERE s.IDLanguage=l.ID)',(int)$jobId);
    }
}
