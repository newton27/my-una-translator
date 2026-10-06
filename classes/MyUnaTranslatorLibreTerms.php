<?php defined('BX_DOL') or die('hack attempt');

/** Explicit interface wording; these corrections are not raw model output. */
final class MyUnaTranslatorLibreTerms
{
    private const TERMS = [
        'sign in'=>['ES'=>'Iniciar sesión','FR'=>'Se connecter','DE'=>'Anmelden','PT-BR'=>'Entrar','ZH-HANS'=>'登录'],
        'sign out'=>['ES'=>'Cerrar sesión','FR'=>'Se déconnecter','DE'=>'Abmelden','PT-BR'=>'Sair','ZH-HANS'=>'退出登录'],
        'send message'=>['ES'=>'Enviar mensaje','FR'=>'Envoyer un message','DE'=>'Nachricht senden','PT-BR'=>'Enviar mensagem','ZH-HANS'=>'发送消息'],
        'resume generation'=>['ES'=>'Reanudar la generación','FR'=>'Reprendre la génération','DE'=>'Generierung fortsetzen','PT-BR'=>'Retomar a geração','ZH-HANS'=>'继续生成'],
        'hello'=>['ES'=>'Hola','FR'=>'Bonjour','DE'=>'Hallo','PT-BR'=>'Olá','ZH-HANS'=>'你好'],
        'you have'=>['ES'=>'tienes','FR'=>'vous avez','DE'=>'Sie haben','PT-BR'=>'você tem','ZH-HANS'=>'您有'],
        'new messages'=>['ES'=>'mensajes nuevos','FR'=>'nouveaux messages','DE'=>'neue Nachrichten','PT-BR'=>'novas mensagens','ZH-HANS'=>'新消息'],
        'new messages.'=>['ES'=>'mensajes nuevos.','FR'=>'nouveaux messages.','DE'=>'neue Nachrichten.','PT-BR'=>'novas mensagens.','ZH-HANS'=>'新消息。'],
        'contact us'=>['ES'=>'contáctenos','FR'=>'contactez-nous','DE'=>'kontaktieren Sie uns','PT-BR'=>'entre em contato conosco','ZH-HANS'=>'联系我们'],
        'visit'=>['ES'=>'Visita','FR'=>'Visitez','DE'=>'Besuchen Sie','PT-BR'=>'Visite','ZH-HANS'=>'访问'],
        'your changes have been saved.'=>['ES'=>'Tus cambios se han guardado.','FR'=>'Vos modifications ont été enregistrées.','DE'=>'Ihre Änderungen wurden gespeichert.','PT-BR'=>'Suas alterações foram salvas.','ZH-HANS'=>'您的更改已保存。'],
        'delete this item?'=>['ES'=>'¿Eliminar este elemento?','FR'=>'Supprimer cet élément ?','DE'=>'Diesen Eintrag löschen?','PT-BR'=>'Excluir este item?','ZH-HANS'=>'删除此条目？'],
    ];

    public static function lookup($source,$target)
    {
        $source=strtolower($source);
        $source=['log in'=>'sign in','login'=>'sign in','log out'=>'sign out','logout'=>'sign out'][$source]??$source;
        return self::TERMS[$source][$target]??null;
    }
}
