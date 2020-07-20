CREATE TABLE `sys_syslog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `body_token` char(32) NOT NULL COMMENT '内容的md5值,防止大规模插入造成大量的无用数据',
  `sentry_file` char(32) NOT NULL COMMENT '文件名字的md5',
  `client_ip` varchar(255) NOT NULL DEFAULT '' COMMENT '客户机器的ip',
  `happen_time` int(11) NOT NULL DEFAULT '0' COMMENT '发生时间',
  `body` text NOT NULL COMMENT '消息体',
  `php_error_level` int(1) NOT NULL DEFAULT '0' COMMENT 'php的错误级别',
  `created_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `updated_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  `deal_state` int(1) NOT NULL DEFAULT '1' COMMENT '状态 1 正常 0 是已经解决',
  `state` int(1) NOT NULL DEFAULT '1' COMMENT '状态 1 正常 0 禁用 -1删除',
  `type` int(1) NOT NULL DEFAULT '0' COMMENT '类别 0位系统日志 1为 php 2为mysql日志 3为nginx日志',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=55718 DEFAULT CHARSET=utf8

alter table sys_syslog add body_token char(32) NOT NULL;