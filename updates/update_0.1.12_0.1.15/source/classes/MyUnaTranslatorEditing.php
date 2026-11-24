<?php defined('BX_DOL') or die('hack attempt');

/**
 * A durable preservation boundary around native Pages editing.
 *
 * Start/finish/markPlanned are called under the Translator advisory lock. Native
 * forms call captureExpected before their unchanged native save; that method
 * keeps the same lock until PHP request shutdown so Finish cannot race the save.
 * Snapshots commit before native writes, including on older nontransactional
 * UNA localization tables. Restoration is comparison-based and retryable.
 */
final class MyUnaTranslatorEditing
{
    private $store;
    private $compile;
    private $invalidate;
    private $captureLockHeld = false;
    private const MAX_SNAPSHOT_VALUES = 500000;

    public function __construct(MyUnaTranslatorStore $store, $compile=null, $invalidate=null)
    {
        $this->store=$store; $this->compile=$compile; $this->invalidate=$invalidate;
    }

    private static function json($value) { return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE); }
    private static function values($json) { $v=json_decode((string)$json,true,512,JSON_THROW_ON_ERROR); return is_array($v)?$v:[]; }
    private static function valueHash($value) { return hash('sha256',$value===null ? "null\0" : "string\0".$value); }
    private function session($id)
    {
        $row=$this->store->row('SELECT * FROM my_una_translator_editing_sessions WHERE id=?',(int)$id);
        if (!$row) throw new RuntimeException('The English editing session was not found.');
        return $row;
    }
    private function activeRow()
    {
        return $this->store->row("SELECT * FROM my_una_translator_editing_sessions WHERE status IN ('active','restoring','conflict') ORDER BY id DESC LIMIT 1");
    }
    private function publicSession(array $row)
    {
        $languages=self::values($row['languages']);
        $result=['id'=>(int)$row['id'],'status'=>$row['status'],'default'=>$row['default_language'],
            'created_at'=>(int)$row['created_at'],'languages'=>$languages,'language_count'=>count($languages),
            'paused_count'=>count(array_filter($languages,fn($l)=>!empty($l['enabled']))),
            'changed_keys'=>(int)$row['changed_keys'],'restored_languages'=>(int)$row['restored_languages'],
            'conflicts'=>(int)$row['conflicts'],'job_id'=>(int)$row['job_id'] ?: null,
            'unsupported_languages'=>self::values($row['unsupported_languages']),
            'error'=>(string)$row['error']];
        if ($result['conflicts']) $result['conflict_details']=$this->store->all("SELECT k.key_name,v.language,v.conflict FROM my_una_translator_editing_values v JOIN my_una_translator_editing_keys k ON k.session_id=v.session_id AND k.key_id=v.key_id WHERE v.session_id=? AND v.conflict<>'' ORDER BY v.key_id,v.language_id LIMIT 20",$result['id']);
        return $result;
    }
    public function getActiveState()
    {
        $row=$this->activeRow();
        if (!$row) return null;
        $state=$this->publicSession($row);
        // Native editor configuration uses current availability; the separate
        // languages snapshot above always retains the original restore target.
        $state['form_languages']=$this->store->all('SELECT ID AS id,Name AS code,Enabled AS enabled FROM sys_localization_languages ORDER BY ID');
        foreach ($state['form_languages'] as &$language) { $language['id']=(int)$language['id']; $language['enabled']=(int)(bool)$language['enabled']; } unset($language);
        return $state;
    }
    public function publicState()
    {
        $r=$this->store->row('SELECT * FROM my_una_translator_editing_sessions ORDER BY id DESC LIMIT 1');
        return ['active'=>$this->getActiveState(),'recent'=>$r?$this->publicSession($r):null];
    }

    /** Resolve builder references using the native localization key comparison. */
    private function builderSources()
    {
        // UNION ALL retains distinct key spellings even when reference columns
        // use a broader collation. Scalar lookups avoid a dependent UNION or a
        // cross-charset join that prevents use of the native key index.
        $references=$this->store->all("SELECT title AS key_name FROM sys_pages_blocks UNION ALL SELECT description FROM sys_pages_blocks
            UNION ALL SELECT content_empty FROM sys_pages_blocks UNION ALL SELECT help FROM sys_pages_blocks
            UNION ALL SELECT content FROM sys_pages_blocks WHERE type='lang'
            UNION ALL SELECT title FROM sys_objects_page UNION ALL SELECT title_system FROM sys_objects_page
            UNION ALL SELECT cover_title FROM sys_objects_page");
        $names=[];
        foreach ($references as $reference) {
            $name=(string)$reference['key_name'];
            if ($name!=='') $names[$name]=$name;
        }
        $keys=[]; $englishId=$this->store->englishId();
        foreach (array_chunk(array_values($names),200) as $chunk) {
            $rows=$this->store->all("SELECT k.ID AS key_id,k.`Key` AS key_name,e.String AS source FROM sys_localization_keys k LEFT JOIN sys_localization_strings e ON e.IDKey=k.ID AND e.IDLanguage=? WHERE k.`Key` IN (".implode(',',array_fill(0,count($chunk),'?')).") AND k.`Key`<>'' ORDER BY k.ID",$englishId,...$chunk);
            foreach ($rows as $row) $keys[(int)$row['key_id']]=$row;
        }
        ksort($keys,SORT_NUMERIC);
        return array_values($keys);
    }
    private function saveKeys($id,array $keys,$atStart)
    {
        foreach (array_chunk($keys,100) as $chunk) {
            $values=[]; $args=[];
            foreach ($chunk as $key) {
                $values[]='(?,?,?,?,?)';
                array_push($args,(int)$id,(int)$key['key_id'],$key['key_name'],$key['source'],(int)$atStart);
            }
            if ($values) $this->store->q('INSERT INTO my_una_translator_editing_keys (session_id,key_id,key_name,source_before,at_start) VALUES '.implode(',',$values),...$args);
        }
    }
    private function saveValues($id,array $languages,$keyId=null)
    {
        foreach ($languages as $language) {
            $sql='INSERT INTO my_una_translator_editing_values (session_id,key_id,language_id,language,original_value) SELECT k.session_id,k.key_id,?,?,s.String FROM my_una_translator_editing_keys k LEFT JOIN sys_localization_strings s ON s.IDKey=k.key_id AND s.IDLanguage=? WHERE k.session_id=?';
            $args=[(int)$language['id'],$language['code'],(int)$language['id'],(int)$id];
            if ($keyId!==null) { $sql.=' AND k.key_id=?'; $args[]=(int)$keyId; }
            $this->store->q($sql,...$args);
        }
    }
    public function start($operatorId,$defaultCode='en')
    {
        if ($defaultCode!=='en') throw new RuntimeException('English editing is available when English is the site default language.');
        if ($this->activeRow()) throw new RuntimeException('An English editing session is already open. Finish restoring it first.');
        if ($this->store->one("SELECT id FROM my_una_translator_editing_sessions WHERE status='finished' AND changed_keys>0 AND job_id=0 LIMIT 1"))
            throw new RuntimeException('Create the translation plan for the previous editing session before starting another.');
        $english=$this->store->language('en');
        if (!$english || empty($english['Enabled'])) throw new RuntimeException('Enable the English default language before starting.');
        $languages=$this->store->all("SELECT ID AS id,Name AS code,Enabled AS enabled FROM sys_localization_languages WHERE Name<>'en' ORDER BY ID");
        foreach ($languages as &$language) { $language['id']=(int)$language['id']; $language['enabled']=(int)(bool)$language['enabled']; } unset($language);
        if (!$languages) throw new RuntimeException('There are no other installed languages to pause.');
        $keys=$this->builderSources();
        if (count($keys)*count($languages)>self::MAX_SNAPSHOT_VALUES) throw new RuntimeException('This site exceeds the 500,000-value editing snapshot limit. No languages were paused.');
        $this->store->q('START TRANSACTION');
        try {
            $this->store->q("INSERT INTO my_una_translator_editing_sessions (created_at,operator_id,default_language,languages,unsupported_languages,compile_pending,error) VALUES (?,?,?,?,?,?,?)",time(),(int)$operatorId,'en',self::json($languages),'[]','[]','');
            $id=$this->store->lastId();
            $this->saveKeys($id,$keys,true); $this->saveValues($id,$languages);
            $this->store->q('COMMIT');
        } catch (Throwable $e) { $this->store->q('ROLLBACK'); throw $e; }
        // Do not move these writes before COMMIT: native tables can be MyISAM.
        try {
            foreach ($languages as $language) if ($language['enabled'])
                $this->store->q('UPDATE sys_localization_languages SET Enabled=0 WHERE ID=? AND BINARY Name=BINARY ? AND Enabled=1',$language['id'],$language['code']);
            if ($this->invalidate) call_user_func($this->invalidate);
        } catch (Throwable $e) {
            try { $this->finish($id); } catch (Throwable $restoreError) {
                $this->store->q('UPDATE my_una_translator_editing_sessions SET error=? WHERE id=?','Pause was interrupted. Finish restoring this session: '.$restoreError->getMessage(),$id);
            }
            throw $e;
        }
        return $this->publicState();
    }

    /** Hold the shared lock through the native request, including identity reads. */
    public function lockForNativeSave($sessionId)
    {
        if (!$this->captureLockHeld) {
            $this->store->lock(); $this->captureLockHeld=true;
            register_shutdown_function(function() {
                if (!$this->captureLockHeld) return;
                try { $this->store->unlock(); } catch (Throwable $e) { /* Closing the database connection releases its advisory lock. */ }
                $this->captureLockHeld=false;
            });
        }
        $session=$this->activeRow();
        if (!$session || (int)$session['id']!==(int)$sessionId || $session['status']!=='active')
            throw new RuntimeException('This English editing session has ended. Reopen the editor before saving.');
        return true;
    }

    /** Record possible native writes before permitting a native form to save. */
    public function captureExpected($sessionId,array $records)
    {
        $this->lockForNativeSave($sessionId);
        $session=$this->activeRow();
        $languages=self::values($session['languages']); $byCode=array_column($languages,null,'code');
        $builder=array_column($this->builderSources(),null,'key_name');
        $this->store->q('START TRANSACTION');
        try {
            foreach ($records as $record) {
                if (!is_array($record) || !is_string($record['key_name']??null) || !is_string($record['language']??null) || !array_key_exists('value',$record) || ($record['value']!==null && !is_string($record['value'])))
                    throw new RuntimeException('An editing form contained invalid translation values. Reopen the editor before saving.');
                if ($record['language']==='en') continue;
                $language=$byCode[$record['language']]??null;
                if (!$language) throw new RuntimeException('The installed languages changed during editing. Finish this session and reopen the editor.');
                $currentLanguage=$this->store->language($language['code']);
                if (!$currentLanguage || (int)$currentLanguage['ID']!==$language['id']) throw new RuntimeException('A protected language changed during editing. Finish restoring the session first.');
                $key=$builder[$record['key_name']]??null;
                if (!$key) continue; // Native page creation assigns its new keys only after validation.
                $saved=$this->store->row('SELECT * FROM my_una_translator_editing_keys WHERE session_id=? AND key_id=?',(int)$sessionId,(int)$key['key_id']);
                if (!$saved) {
                    if ((int)$this->store->one('SELECT COUNT(*) FROM my_una_translator_editing_values WHERE session_id=?',(int)$sessionId)+count($languages)>self::MAX_SNAPSHOT_VALUES)
                        throw new RuntimeException('The editing snapshot limit was reached. Finish restoring this session before creating more blocks.');
                    $this->saveKeys($sessionId,[$key],false); $this->saveValues($sessionId,$languages,$key['key_id']);
                } elseif ($saved['key_name']!==$key['key_name']) throw new RuntimeException('A protected language key was replaced. Finish restoring this session first.');
                if ($record['value']===null && $this->store->one("SELECT key_id FROM my_una_translator_editing_values WHERE session_id=? AND key_id=? AND original_value IS NOT NULL AND original_value<>'' LIMIT 1",(int)$sessionId,(int)$key['key_id']))
                    throw new RuntimeException('This save would delete a key containing protected translations. Keep its existing translated values or finish the editing session first.');
                $hash=self::valueHash($record['value']);
                if (!$this->store->one('SELECT value_hash FROM my_una_translator_editing_expected WHERE session_id=? AND key_id=? AND language_id=? AND value_hash=?',(int)$sessionId,(int)$key['key_id'],$language['id'],$hash))
                    $this->store->q('INSERT INTO my_una_translator_editing_expected (session_id,key_id,language_id,value_hash) VALUES (?,?,?,?)',(int)$sessionId,(int)$key['key_id'],$language['id'],$hash);
            }
            $this->store->q('COMMIT');
        } catch (Throwable $e) { $this->store->q('ROLLBACK'); throw $e; }
        return true;
    }

    private function setRestored(array $value,$error='')
    {
        $this->store->q('UPDATE my_una_translator_editing_values SET restored=?,conflict=? WHERE session_id=? AND key_id=? AND language_id=?',$error===''?1:0,$error,(int)$value['session_id'],(int)$value['key_id'],(int)$value['language_id']);
    }
    private function restoreOne(array $value,array $languages)
    {
        $original=$value['original_value'];
        $key=$this->store->row('SELECT `Key` AS key_name FROM sys_localization_keys WHERE ID=?',(int)$value['key_id']);
        if (!$key || $key['key_name']!==$value['key_name']) {
            $this->setRestored($value,$original!==null && $original!==''?'The key was deleted or replaced; its saved translation remains in the editing archive.':''); return;
        }
        if (!isset($languages[$value['language']]) || $languages[$value['language']]!==(int)$value['language_id']) {
            $this->setRestored($value,'The installed language was removed or replaced; its saved translation remains in the editing archive.'); return;
        }
        $current=$this->store->valueNow($value['key_id'],$value['language_id']);
        if ($current===$original) { $this->setRestored($value); return; }
        if (!$this->store->one('SELECT value_hash FROM my_una_translator_editing_expected WHERE session_id=? AND key_id=? AND language_id=? AND value_hash=?',(int)$value['session_id'],(int)$value['key_id'],(int)$value['language_id'],self::valueHash($current))) {
            $this->setRestored($value,'This translation changed outside the recorded editing saves. The current text and saved original were both kept.'); return;
        }
        $identity=' AND EXISTS (SELECT 1 FROM sys_localization_keys WHERE ID=? AND BINARY `Key`=BINARY ?) AND EXISTS (SELECT 1 FROM sys_localization_languages WHERE ID=? AND BINARY Name=BINARY ?)';
        $identityArgs=[(int)$value['key_id'],$value['key_name'],(int)$value['language_id'],$value['language']];
        if ($current===null) {
            if ($original!==null) $this->store->q('INSERT INTO sys_localization_strings (IDKey,IDLanguage,String) SELECT ?,?,? WHERE NOT EXISTS (SELECT 1 FROM sys_localization_strings WHERE IDKey=? AND IDLanguage=?)'.$identity,(int)$value['key_id'],(int)$value['language_id'],$original,(int)$value['key_id'],(int)$value['language_id'],...$identityArgs);
        } elseif ($original===null) {
            $this->store->q('DELETE FROM sys_localization_strings WHERE IDKey=? AND IDLanguage=? AND BINARY String=BINARY ?'.$identity,(int)$value['key_id'],(int)$value['language_id'],$current,...$identityArgs);
        } else $this->store->q('UPDATE sys_localization_strings SET String=? WHERE IDKey=? AND IDLanguage=? AND BINARY String=BINARY ?'.$identity,$original,(int)$value['key_id'],(int)$value['language_id'],$current,...$identityArgs);
        $sameKey=$this->store->one('SELECT ID FROM sys_localization_keys WHERE ID=? AND BINARY `Key`=BINARY ?',(int)$value['key_id'],$value['key_name']);
        $sameLanguage=$this->store->one('SELECT ID FROM sys_localization_languages WHERE ID=? AND BINARY Name=BINARY ?',(int)$value['language_id'],$value['language']);
        $this->setRestored($value,$sameKey && $sameLanguage && $this->store->valueNow($value['key_id'],$value['language_id'])===$original?'':'The translation changed during restoration. Its saved original remains in the editing archive.');
    }
    private function recordFinalSources($id)
    {
        $keys=$this->builderSources();
        $this->store->q('UPDATE my_una_translator_editing_keys SET changed=0,source_after=NULL WHERE session_id=?',(int)$id);
        foreach ($keys as $key) {
            $saved=$this->store->row('SELECT key_name,source_before,at_start FROM my_una_translator_editing_keys WHERE session_id=? AND key_id=?',(int)$id,(int)$key['key_id']);
            if (!$saved) { $this->saveKeys($id,[$key],false); $saved=['key_name'=>$key['key_name'],'source_before'=>null,'at_start'=>0]; }
            // A reused ID is a restoration conflict, never a replacement for the archive identity.
            if ($saved['key_name']!==$key['key_name']) continue;
            $changed=$key['source']!==null && trim($key['source'])!=='' && (!(int)$saved['at_start'] || $saved['source_before']!==$key['source']);
            $this->store->q('UPDATE my_una_translator_editing_keys SET source_after=?,changed=? WHERE session_id=? AND key_id=?',$key['source'],(int)$changed,(int)$id,(int)$key['key_id']);
        }
        return (int)$this->store->one('SELECT COUNT(*) FROM my_una_translator_editing_keys WHERE session_id=? AND changed=1',(int)$id);
    }

    public function finish($sessionId)
    {
        $session=$this->session($sessionId); $id=(int)$session['id'];
        if (in_array($session['status'],['finished','planned'],true)) return $this->publicState();
        if (!in_array($session['status'],['active','restoring','conflict'],true)) throw new RuntimeException('This editing session cannot be restored.');
        $resumeCompile=$session['status']==='restoring' && !empty($session['restore_complete']);
        $this->store->q("UPDATE my_una_translator_editing_sessions SET status='restoring',restore_complete=?,error='' WHERE id=?",(int)$resumeCompile,$id);
        if (!$resumeCompile) {
            $languages=self::values($session['languages']);
            $currentLanguages=[];
            foreach ($this->store->all('SELECT ID,Name FROM sys_localization_languages') as $row) $currentLanguages[$row['Name']]=(int)$row['ID'];
            $afterKey=0; $afterLanguage=0;
            do {
                $values=$this->store->all('SELECT v.*,k.key_name FROM my_una_translator_editing_values v JOIN my_una_translator_editing_keys k ON k.session_id=v.session_id AND k.key_id=v.key_id WHERE v.session_id=? AND (v.key_id>? OR (v.key_id=? AND v.language_id>?)) ORDER BY v.key_id,v.language_id LIMIT 200',$id,$afterKey,$afterKey,$afterLanguage);
                foreach ($values as $value) { $this->restoreOne($value,$currentLanguages); $afterKey=(int)$value['key_id']; $afterLanguage=(int)$value['language_id']; }
            } while (count($values)===200);
            $restored=0; $compile=[]; $languageConflicts=0;
            foreach ($languages as $language) {
                if (($currentLanguages[$language['code']]??null)!==$language['id']) { ++$languageConflicts; continue; }
                $this->store->q('UPDATE sys_localization_languages SET Enabled=? WHERE ID=? AND BINARY Name=BINARY ?',$language['enabled'],$language['id'],$language['code']);
                $compile[]=$language['code'];
                if ($language['enabled']) ++$restored;
            }
            $this->store->q('START TRANSACTION');
            try {
                $changed=$this->recordFinalSources($id);
                $conflicts=(int)$this->store->one("SELECT COUNT(*) FROM my_una_translator_editing_values WHERE session_id=? AND conflict<>''",$id)+$languageConflicts;
                $this->store->q('UPDATE my_una_translator_editing_sessions SET changed_keys=?,restored_languages=?,restore_complete=1,conflicts=?,compile_pending=?,error=? WHERE id=?',$changed,$restored,$conflicts,self::json($compile),$languageConflicts?'An installed language was removed or replaced. Saved language states were retained for review.':'',$id);
                $this->store->q('COMMIT');
            } catch (Throwable $e) { $this->store->q('ROLLBACK'); throw $e; }
        }
        $session=$this->session($id); $pending=self::values($session['compile_pending']);
        try {
            if ($this->invalidate) call_user_func($this->invalidate);
            while ($pending) {
                $code=$pending[0];
                if ($this->compile && call_user_func($this->compile,$code)===false)
                    throw new RuntimeException('Could not compile restored language '.$code.'. Finish restoring this session again to retry.');
                array_shift($pending);
                $this->store->q('UPDATE my_una_translator_editing_sessions SET compile_pending=? WHERE id=?',self::json($pending),$id);
            }
            if ($this->invalidate) call_user_func($this->invalidate);
        } catch (Throwable $e) {
            $this->store->q('UPDATE my_una_translator_editing_sessions SET error=? WHERE id=?',$e->getMessage(),$id); throw $e;
        }
        $this->store->q('UPDATE my_una_translator_editing_sessions SET status=?,finished_at=? WHERE id=?',(int)$session['conflicts']?'conflict':'finished',time(),$id);
        return $this->publicState();
    }

    /** Final source scope is frozen when restoration completes, not at plan time. */
    public function affectedSources($sessionId)
    {
        $session=$this->session($sessionId);
        if (!in_array($session['status'],['finished','planned'],true)) throw new RuntimeException('Finish restoring this editing session before creating its translation plan.');
        $rows=$this->store->all('SELECT key_id,key_name,source_after AS source FROM my_una_translator_editing_keys WHERE session_id=? AND changed=1 ORDER BY key_id',(int)$sessionId);
        foreach ($rows as &$row) $row['key_id']=(int)$row['key_id']; unset($row);
        return $rows;
    }
    public function targets($sessionId)
    {
        $languages=self::values($this->session($sessionId)['languages']);
        return array_values(array_map(fn($l)=>$l['code'],array_filter($languages,fn($l)=>!empty($l['enabled']))));
    }
    public function markPlanned($sessionId,$jobId,array $unsupported=[])
    {
        $session=$this->session($sessionId);
        if ($session['status']==='planned' && (int)$session['job_id']===(int)$jobId) return;
        if ($session['status']!=='finished') throw new RuntimeException('Finish restoring this editing session before creating its translation plan.');
        $this->store->q("UPDATE my_una_translator_editing_sessions SET status='planned',job_id=?,unsupported_languages=? WHERE id=?",(int)$jobId,self::json(array_values($unsupported)),(int)$sessionId);
    }

    /** An operator may leave the finished scope untranslated without losing its archive. */
    public function dismiss($sessionId)
    {
        $session=$this->session($sessionId);
        if ($session['status']==='dismissed') return $this->publicState();
        if ($session['status']!=='finished' || (int)$session['job_id'])
            throw new RuntimeException('Only a fully restored session without a saved translation plan can be skipped.');
        $this->store->q("UPDATE my_una_translator_editing_sessions SET status='dismissed' WHERE id=?",(int)$sessionId);
        return $this->publicState();
    }

    /** Called in the same transaction as explicit deletion of a linked job. */
    public function forgetJob($jobId)
    {
        $this->store->q("UPDATE my_una_translator_editing_sessions SET status='dismissed',job_id=0 WHERE job_id=? AND status='planned'",(int)$jobId);
    }
}
