<?php defined('BX_DOL') or die('hack attempt');
bx_import('BxTemplMenuSwitchLanguage');

/** Add native language records created by Translator to UNA's switcher. */
class MyUnaTranslatorMenuSwitchLanguage extends BxTemplMenuSwitchLanguage
{
    protected function loadData()
    {
        parent::loadData();
        $languages=BxDolDb::getInstance()->getAll("SELECT l.Name,l.Title
            FROM sys_localization_languages l
            WHERE l.Enabled=1 AND l.Name<>'en' ORDER BY l.Title");
        if (!$languages) return;

        $items=$this->_aObject['menu_items']??[];
        $seen=array_column($items,'id');
        $modules=BxDolModuleQuery::getInstance();
        list($base,$params)=$this->{'getBaseUrl'.bx_gen_method_name($this->_sType)}();
        $permalinks=BxDolPermalinks::getInstance();
        $titleMethod='getItemTitle'.bx_gen_method_name($this->_sType);
        foreach ($languages as $language) {
            $code=$language['Name'];
            // A separately installed language module retains its native visibility rules.
            if (in_array($code,$seen,true) || $modules->getModuleByUri($code)) continue;
            $params['lang']=$code;
            $items[]=[
                'id'=>$code,'name'=>'lang-'.$code,'class'=>'',
                'title'=>$this->$titleMethod($code,$language['Title']),
                'target'=>'_self','icon'=>'',
                'link'=>bx_html_attribute($permalinks->permalink(bx_append_url_params($base,$params))),
                'onclick'=>''
            ];
        }
        $this->_aObject['menu_items']=$items;
    }
}
