<?php defined('BX_DOL') or die('hack attempt');

/** Explicit interface wording; these corrections are not raw model output. */
final class MyUnaTranslatorLibreTerms
{
    private const TERMS = [
        'default captcha'=>['ZH-HANT'=>'預設驗證碼'],
        'sign in'=>['ES'=>'Iniciar sesión','FR'=>'Se connecter','DE'=>'Anmelden','PT-BR'=>'Entrar','ZH-HANS'=>'登录'],
        'sign out'=>['ES'=>'Cerrar sesión','FR'=>'Se déconnecter','DE'=>'Abmelden','PT-BR'=>'Sair','ZH-HANS'=>'退出登录'],
        'send message'=>['ES'=>'Enviar mensaje','FR'=>'Envoyer un message','DE'=>'Nachricht senden','PT-BR'=>'Enviar mensagem','ZH-HANS'=>'发送消息'],
        'resume'=>['ES'=>'Reanudar','FR'=>'Reprendre','DE'=>'Fortsetzen','PT-BR'=>'Retomar','ZH-HANS'=>'继续'],
        'resume generation'=>['ES'=>'Reanudar la generación','FR'=>'Reprendre la génération','DE'=>'Generierung fortsetzen','PT-BR'=>'Retomar a geração','ZH-HANS'=>'继续生成'],
        'hello'=>['ES'=>'Hola','FR'=>'Bonjour','DE'=>'Hallo','PT-BR'=>'Olá','ZH-HANS'=>'你好'],
        'you have'=>['ES'=>'tienes','FR'=>'vous avez','DE'=>'Sie haben','PT-BR'=>'você tem','ZH-HANS'=>'您有'],
        'new messages'=>['ES'=>'mensajes nuevos','FR'=>'nouveaux messages','DE'=>'neue Nachrichten','PT-BR'=>'novas mensagens','ZH-HANS'=>'新消息'],
        'new messages.'=>['ES'=>'mensajes nuevos.','FR'=>'nouveaux messages.','DE'=>'neue Nachrichten.','PT-BR'=>'novas mensagens.','ZH-HANS'=>'新消息。'],
        'contact us'=>['ES'=>'contáctenos','FR'=>'contactez-nous','DE'=>'kontaktieren Sie uns','PT-BR'=>'entre em contato conosco','ZH-HANS'=>'联系我们'],
        'visit'=>['ES'=>'Visita','FR'=>'Visitez','DE'=>'Besuchen Sie','PT-BR'=>'Visite','ZH-HANS'=>'访问'],
        'your changes have been saved.'=>['ES'=>'Tus cambios se han guardado.','FR'=>'Vos modifications ont été enregistrées.','DE'=>'Ihre Änderungen wurden gespeichert.','PT-BR'=>'Suas alterações foram salvas.','ZH-HANS'=>'您的更改已保存。'],
        'delete this item?'=>['ES'=>'¿Eliminar este elemento?','FR'=>'Supprimer cet élément ?','DE'=>'Diesen Eintrag löschen?','PT-BR'=>'Excluir este item?','ZH-HANS'=>'删除此条目？'],
        'create a new post'=>['ES'=>'Crear una publicación','FR'=>'Créer une publication','DE'=>'Neuen Beitrag erstellen','PT-BR'=>'Criar uma publicação','ZH-HANS'=>'创建新帖子'],
        'roll back changes'=>['ES'=>'Revertir cambios','FR'=>'Annuler les modifications','DE'=>'Änderungen zurücksetzen','PT-BR'=>'Desfazer alterações','ZH-HANS'=>'回滚更改'],
        'payment failed. your card was not charged.'=>['ES'=>'El pago falló. No se realizó ningún cargo a tu tarjeta.','FR'=>'Le paiement a échoué. Votre carte n’a pas été débitée.','DE'=>'Die Zahlung ist fehlgeschlagen. Ihre Karte wurde nicht belastet.','PT-BR'=>'O pagamento falhou. Seu cartão não foi cobrado.','ZH-HANS'=>'付款失败，未向您的银行卡扣款。'],
        'the job is paused because the character limit was reached.'=>['ES'=>'El trabajo está en pausa porque se alcanzó el límite de caracteres.','FR'=>'Le travail est en pause, car la limite de caractères a été atteinte.','DE'=>'Der Auftrag wurde pausiert, weil das Zeichenlimit erreicht wurde.','PT-BR'=>'O trabalho foi pausado porque o limite de caracteres foi atingido.','ZH-HANS'=>'任务已暂停，因为已达到字符数限制。'],
        'character limit'=>['ES'=>'Límite de caracteres','FR'=>'Limite de caractères','DE'=>'Zeichenlimit','PT-BR'=>'Limite de caracteres','ZH-HANS'=>'字符数限制'],
        'translation job'=>['ES'=>'Trabajo de traducción','FR'=>'Travail de traduction','DE'=>'Übersetzungsauftrag','PT-BR'=>'Trabalho de tradução','ZH-HANS'=>'翻译任务'],
    ];

    public static function lookup($source,$target)
    {
        $source=strtolower(preg_replace('/[ \t\r\n]+/', ' ', trim($source)));
        $source=['log in'=>'sign in','login'=>'sign in','log out'=>'sign out','logout'=>'sign out'][$source]??$source;
        return self::TERMS[$source][$target]??null;
    }
}
