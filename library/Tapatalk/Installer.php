<?php
class Tapatalk_Installer
{
    protected static $table = array(
    'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_tapatalk_users` (
                `userid` INT( 10 ) NOT NULL,
                `announcement` SMALLINT( 5 ) NOT NULL DEFAULT 1,
                `pm` SMALLINT( 5 ) NOT NULL DEFAULT 1,
                `subscribe` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `quote` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `liked` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `tag` SMALLINT ( 5 ) NOT NULL DEFAULT 1,
                `updated` TIMESTAMP NOT NULL,
                PRIMARY KEY (`userid`)
                )
            ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
    'dropQuery' => 'DROP TABLE IF EXISTS `xf_tapatalk_users`'
    );

    /**
    * This is the function to create a table in the database so our addon will work.
    *
    * @since Version 1.0.0
    * @version 1.0.0
    * @author Euhow
    */
    public static function install()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['createQuery']);
    }



    /**
    * This is the function to DELETE the table of our addon in the database.
    *
    * @since Version 1.0.0
    * @version 1.0.0
    * @author Euhow
    */
    public static function uninstall()
    {
        $db = XenForo_Application::get('db');
        $db->query(self::$table['dropQuery']);
    }
}
?>