#!/bin/sh

user="$1";
password="$2";

zapros="CREATE DATABASE IF NOT EXISTS asteriskcdrdb;
CREATE TABLE IF NOT EXISTS \`asteriskcdrdb\`.\`cel\` (
  \`id\` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  \`eventtype\` VARCHAR(30) COLLATE utf8_unicode_ci NOT NULL,
  \`eventtime\` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
   ON UPDATE CURRENT_TIMESTAMP,
  \`userdeftype\` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  \`cid_name\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`cid_num\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`cid_ani\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`cid_rdnis\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`cid_dnid\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`exten\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`context\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`channame\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`appname\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`appdata\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  \`amaflags\` INT(11) NOT NULL,
  \`accountcode\` VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  \`peeraccount\` VARCHAR(20) COLLATE utf8_unicode_ci NOT NULL,
  \`uniqueid\` VARCHAR(150) COLLATE utf8_unicode_ci NOT NULL,
  \`linkedid\` VARCHAR(150) COLLATE utf8_unicode_ci NOT NULL,
  \`userfield\` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
  \`peer\` VARCHAR(80) COLLATE utf8_unicode_ci NOT NULL,
  UNIQUE KEY \`id\` (\`id\`)
) ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
CREATE TABLE IF NOT EXISTS \`asteriskcdrdb\`.\`PT1C_cdr\` (
   \`id\` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
   \`calldate\` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
   \`clid\` VARCHAR(80) NOT NULL DEFAULT '',
   \`src\` VARCHAR(80) NOT NULL DEFAULT '',
   \`dst\` VARCHAR(80) NOT NULL DEFAULT '',
   \`dcontext\` VARCHAR(80) NOT NULL DEFAULT '',
   \`lastapp\` VARCHAR(200) NOT NULL DEFAULT '',
   \`lastdata\` VARCHAR(200) NOT NULL DEFAULT '',
   \`duration\` FLOAT UNSIGNED NULL DEFAULT NULL,
   \`billsec\` FLOAT UNSIGNED NULL DEFAULT NULL,
   \`disposition\` ENUM('ANSWERED','BUSY','FAILED','NO ANSWER','CONGESTION') NULL DEFAULT NULL,
   \`channel\` VARCHAR(50) NULL DEFAULT NULL,
   \`dstchannel\` VARCHAR(50) NULL DEFAULT NULL,
   \`amaflags\` VARCHAR(50) NULL DEFAULT NULL,
   \`accountcode\` VARCHAR(20) NULL DEFAULT NULL,
   \`uniqueid\` VARCHAR(32) NOT NULL DEFAULT '',
   \`userfield\` VARCHAR(200) NOT NULL DEFAULT '',
   \`did\` VARCHAR(200) NOT NULL DEFAULT '',
   \`answer\` DATETIME NOT NULL,
   \`end\` DATETIME NOT NULL,
   \`recordingfile\` varchar(255) NOT NULL default '',
   \`peeraccount\` varchar(20) NOT NULL default '',
   \`linkedid\` varchar(32) NOT NULL default '',
   \`sequence\` int(11) NOT NULL default '0',       
   PRIMARY KEY (\`id\`),
   INDEX \`calldate\` (\`calldate\`),
   INDEX \`dst\` (\`dst\`),
   INDEX \`src\` (\`src\`),
   INDEX \`dcontext\` (\`dcontext\`),
   INDEX \`clid\` (\`clid\`)
)ENGINE=INNODB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";

mysql -sse "$zapros" -u"$user" -p"$password";
