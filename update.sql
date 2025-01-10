CREATE TABLE IF NOT EXISTS `pay_complain` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `paytype` int(11) NOT NULL,
  `channel` int(11) NOT NULL,
  `subchannel` int(11) NOT NULL DEFAULT '0',
  `source` tinyint(1) NOT NULL DEFAULT '0',
  `uid` int(11) NOT NULL,
  `trade_no` char(19) NOT NULL,
  `thirdid` varchar(100) NOT NULL,
  `type` varchar(30) NOT NULL,
  `title` varchar(300) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `status` varchar(30) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `edittime` datetime DEFAULT NULL,
  `thirdmchid` varchar(30) DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `uid` (`uid`),
 UNIQUE KEY `thirdid` (`thirdid`),
 KEY `addtime` (`addtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pay_applychannel` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `type` varchar(20) NOT NULL,
  `channel` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `desc` varchar(200) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `sort` int(10) NOT NULL DEFAULT 0,
  `gid` int(11) unsigned NOT NULL DEFAULT '0',
  `addtime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `config` text DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pay_applymerchant` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `cid` int(11) unsigned NOT NULL,
  `uid` int(11) unsigned NOT NULL,
  `orderid` char(16) NOT NULL,
  `thirdid` varchar(150) DEFAULT NULL,
  `mchtype` tinyint(4) unsigned NOT NULL,
  `mchname` varchar(150) NOT NULL,
  `mchid` varchar(80) DEFAULT NULL,
  `addtime` datetime NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `paid` tinyint(1) NOT NULL DEFAULT '0',
  `info` text DEFAULT NULL,
  `ext` text DEFAULT NULL,
  `reason` varchar(200) DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `uid` (`uid`),
 KEY `orderid` (`orderid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;