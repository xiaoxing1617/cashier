#星益云www.96xy.cn;</explode>

CREATE TABLE `xy_cashier_captcha` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `only` varchar(255) DEFAULT NULL COMMENT '唯一码',
  `code` varchar(255) DEFAULT NULL COMMENT '验证码',
  `type` varchar(255) DEFAULT NULL COMMENT '验证码类型',
  `value1` varchar(255) DEFAULT NULL COMMENT '相关值1',
  `creation_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `expiration_time` timestamp NULL DEFAULT NULL COMMENT '过期时间',
  `captcha_time` timestamp NULL DEFAULT NULL COMMENT '验证时间',
  `ip` varchar(255) DEFAULT NULL COMMENT 'IP地址',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='验证码列表';</explode>


CREATE TABLE `xy_cashier_core` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `value1` text,
  `tips` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='核心表';</explode>


INSERT INTO `xy_cashier_core` VALUES (1,'title','星益云聚合收银台系统','安全密码'),(2,'keyword','星益云聚合收银台系统,合并收款码,聚合收银台,星益云,收银台,小星,收款码,聚合支付,易支付','网站关键字'),(3,'describe','星益云聚合收银台系统是一款聚合全网支付平台收款的系统，集合了多种支付方式，方便快捷，真正的一码打通全网支付平台！','网站描述'),(4,'subtitle','一码打通全网支付平台','网站副标题'),(5,'home_template','1.css','网站首页模板'),(6,'admin_account','admin','后台账号'),(7,'admin_password','e10adc3949ba59abbe56e057f20f883e','后台密码'),(8,'return_template','default','支付回调模板'),(9,'login_user_template','default','用户登录模板'),(10,'login_admin_template','default','后台登录模板'),(11,'register_template','default','注册模板'),(12,'register_open','1','注册开关'),(13,'email_host','smtp.163.com','SMTP服务器'),(14,'email_user','l1617499825@163.com','邮箱地址'),(15,'email_pass','','授权码'),(16,'email_port','465','服务器端口'),(17,'index_template','originality','首页模板'),(18,'qq','2220667704','站长QQ'),(19,'demo_pay','lovelyBlue','演示收款码模板【0关闭】'),(20,'demo_uid','94001','演示收款UID'),(21,'free_collection_service_days','7','注册免费赠送收款能力天数'),(22,'collection_cost','10.00','收款能力价格/月'),(23,'system_uid','94001','续费充值的UID'),(24,'veloce_qqlogin','1','QQ快捷登录开关'),(25,'key','1e630135ba17757d13a07b48297ebe39',NULL),(26,'security_password','4297f44b13955235245b2497399d7a93','安全密码'),(27,'bottom_info','Copyright © 2021 <a href=\"#\">星益云</a> 版权所有','底部信息'),(28,'order_prefix','XY','订单号前缀'),(29,'links','[{\"name\":\"\\u661f\\u76ca\\u4e91\",\"href\":\"http:\\/\\/www.96xy.cn\\/\"},{\"name\":\"\\u652f\\u4ed8\\u5b9d\\u5f00\\u653e\\u5e73\\u53f0\",\"href\":\"https:\\/\\/open.alipay.com\\/\"}]',NULL),(30,'thinkapi_appcode','','thinkapi的AppCode'),(31,'text_review','0','文本审核开关');</explode>


CREATE TABLE `xy_cashier_illegal_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mode` int(11) DEFAULT NULL COMMENT '违规类型',
  `uid` varchar(255) DEFAULT NULL COMMENT '违规商户UID',
  `content` text COMMENT '违规内容',
  `source` varchar(255) DEFAULT NULL COMMENT '拦截来源',
  `words` text COMMENT '违规关键字',
  `illegal_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '违规时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;</explode>

CREATE TABLE `xy_cashier_login` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) DEFAULT NULL COMMENT '用户UID',
  `time` varchar(255) DEFAULT NULL COMMENT '登录时间',
  `ip` varchar(255) DEFAULT NULL COMMENT '登录IP',
  `mode` int(11) NOT NULL DEFAULT '0' COMMENT '登录方式',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='登录记录';</explode>

CREATE TABLE `xy_cashier_notice` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) DEFAULT NULL COMMENT '发布人',
  `group` varchar(255) DEFAULT NULL COMMENT '公告群体',
  `popup` int(11) DEFAULT '0' COMMENT '是否弹窗',
  `topping` int(11) DEFAULT '0' COMMENT '是否置顶',
  `confirm` int(11) DEFAULT '0' COMMENT '是否必须确认',
  `title` varchar(255) DEFAULT NULL COMMENT '公告标题',
  `content` text COMMENT '公告内容',
  `show` int(11) DEFAULT '1' COMMENT '是否展示',
  `establish_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `release_time` timestamp NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='公告表';</explode>

CREATE TABLE `xy_cashier_notice_record` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nid` varchar(255) DEFAULT NULL COMMENT '公告ID',
  `uid` varchar(255) DEFAULT NULL COMMENT '商户UID',
  `popup` int(11) DEFAULT '0' COMMENT '是否已弹出过',
  `confirm` int(11) DEFAULT '0' COMMENT '是否已确认过',
  `fabulous` int(11) DEFAULT '0' COMMENT '是否点赞',
  `negative` int(11) DEFAULT '0' COMMENT '是否踩',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;</explode>

CREATE TABLE `xy_cashier_order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) NOT NULL DEFAULT '0' COMMENT '用户uid',
  `pid` varchar(255) DEFAULT NULL COMMENT '支付接口ID',
  `type` varchar(255) DEFAULT NULL COMMENT '支付方式',
  `money` decimal(10,2) DEFAULT '0.00' COMMENT '支付金额',
  `service_trade_no` varchar(255) DEFAULT NULL COMMENT '服务订单号',
  `third_trade_no` varchar(255) DEFAULT NULL COMMENT '第三方订单号',
  `out_trade_no` varchar(255) DEFAULT NULL COMMENT '系统订单号',
  `api_trade_no` varchar(255) DEFAULT NULL COMMENT '接口订单号',
  `name` varchar(255) DEFAULT NULL COMMENT '支付名称',
  `creation_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `payment_time` timestamp NULL DEFAULT NULL COMMENT '支付时间',
  `expiration_time` timestamp NULL DEFAULT NULL COMMENT '过期时间',
  `close_time` timestamp NULL DEFAULT NULL COMMENT '交易关闭时间',
  `remarks` varchar(255) DEFAULT NULL COMMENT '订单备注',
  `return_url` varchar(255) DEFAULT NULL COMMENT '同步通知地址',
  `notify_url` varchar(255) DEFAULT NULL COMMENT '异步通知地址',
  `trade_type` varchar(255) DEFAULT NULL COMMENT '支付场景',
  `refund_money` decimal(10,2) DEFAULT '0.00' COMMENT '退款金额',
  `refund_time` timestamp NULL DEFAULT NULL COMMENT '退款时间',
  `ip` varchar(255) DEFAULT NULL COMMENT 'IP地址',
  `notify_num` int(11) NOT NULL DEFAULT '0' COMMENT '异步通知次数',
  `next_notify_time` timestamp NULL DEFAULT NULL COMMENT '下次异步通知时间',
  `notify_end` int(11) DEFAULT '0' COMMENT '是否异步通知（1是0否）',
  `buyer` varchar(255) DEFAULT NULL COMMENT '买家标识',
  `source` varchar(255) DEFAULT NULL COMMENT '订单来源类型',
  `state` int(11) NOT NULL DEFAULT '0' COMMENT '支付状态',
  `faid` int(11) DEFAULT '0' COMMENT '固额码ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='订单表';</explode>

CREATE TABLE `xy_cashier_pay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL COMMENT '接口名称',
  `uid` varchar(255) DEFAULT NULL COMMENT '创建用户uid',
  `type` varchar(255) DEFAULT NULL COMMENT '支付平台（支付方式）',
  `plug_in` varchar(255) DEFAULT NULL COMMENT '接口插件',
  `min_money` varchar(255) NOT NULL DEFAULT '0.01' COMMENT '至少金额',
  `max_money` varchar(255) NOT NULL DEFAULT '9999999' COMMENT '最多金额',
  `value1` text COMMENT '值1',
  `value2` text COMMENT '值2',
  `value3` text COMMENT '值3',
  `value4` text COMMENT '值4',
  `value5` text COMMENT '值5',
  `value6` text COMMENT '值6',
  `value7` text COMMENT '值7',
  `value8` text COMMENT '值8',
  `value9` text COMMENT '值9',
  `value10` text COMMENT '值10',
  `creation_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `state` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='接口表';</explode>

CREATE TABLE `xy_cashier_service` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) DEFAULT NULL COMMENT '用户ID',
  `mode` varchar(255) DEFAULT NULL COMMENT '服务费类型',
  `service_trade_no` varchar(255) DEFAULT NULL COMMENT '服务订单号',
  `type` varchar(255) DEFAULT NULL COMMENT '支付方式',
  `money` varchar(255) DEFAULT NULL COMMENT '支付金额',
  `month` varchar(255) DEFAULT NULL COMMENT '购买月数',
  `creation_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `remarks` varchar(255) DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='续费表';</explode>

CREATE TABLE `xy_cashier_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(255) DEFAULT NULL COMMENT '用户编号',
  `key` varchar(255) DEFAULT NULL COMMENT '商户密钥',
  `code` varchar(255) DEFAULT NULL COMMENT '用户代码',
  `email` varchar(255) NOT NULL DEFAULT '' COMMENT '用户邮箱',
  `account` varchar(255) NOT NULL DEFAULT '' COMMENT '用户账号',
  `password` text NOT NULL COMMENT '用户密码',
  `nickname` varchar(255) NOT NULL DEFAULT '星益云聚合收银台系统' COMMENT '用户名称',
  `qq` varchar(255) DEFAULT NULL COMMENT '用户QQ',
  `weixin` varchar(255) DEFAULT NULL COMMENT '用户微信号',
  `cashier_template` varchar(255) NOT NULL DEFAULT 'default' COMMENT '用户收银台模板',
  `creation_time` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `pay_interface` text COMMENT '(json)支付接口',
  `code_reset_time` timestamp NULL DEFAULT NULL COMMENT '上次重置收款码时间',
  `veloce_qq` varchar(255) DEFAULT NULL COMMENT '快捷登录QQ',
  `preselection_money` varchar(255) DEFAULT NULL COMMENT '预选金额',
  `security_password` varchar(255) NOT NULL DEFAULT '4297f44b13955235245b2497399d7a93' COMMENT '安全密码',
  `payment_mode` int(11) NOT NULL DEFAULT '1' COMMENT '支付商品规则',
  `payment_name` varchar(255) DEFAULT NULL COMMENT '支付商品自义定名称',
  `state` int(11) NOT NULL DEFAULT '1' COMMENT '用户状态',
  `expiration_time` timestamp NULL DEFAULT NULL COMMENT '到期时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户表';</explode>

INSERT INTO `xy_cashier_core` (`id`, `name`, `value1`, `tips`) VALUES (NULL, 'captcha_switch', 'admin_login|user_login|user_register', '验证码开关（在里面代表开启）');</explode>

INSERT INTO `xy_cashier_core` (`id`, `name`, `value1`, `tips`) VALUES (NULL, 'captcha_code', '2345678abcdefhijkmnpqrstuvwxyzABCDEFGHJKLMNPQRTUVWXY', '验证码字符集合');</explode>

CREATE TABLE `xy_cashier_ad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `top_level` varchar(255) DEFAULT NULL COMMENT '顶级分类',
  `two_level` varchar(255) DEFAULT NULL COMMENT '二级分类',
  `alias` varchar(255) DEFAULT NULL COMMENT '别名（唯一）',
  `data` text COMMENT '数据',
  `state` int(11) DEFAULT '1' COMMENT '状态（0关1开）',
  `time_start` timestamp NULL DEFAULT NULL COMMENT '开始时间',
  `time_end` timestamp NULL DEFAULT NULL COMMENT '结束时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='广告';</explode>


CREATE TABLE `xy_cashier_fixed_amount` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`code` varchar(255) DEFAULT NULL COMMENT '固额码code',
`uid` varchar(255) DEFAULT NULL COMMENT '创建用户/收款用户',
`money` int(11) NOT NULL DEFAULT '0' COMMENT '支付金额（*100）',
`pay_type` varchar(255) DEFAULT NULL COMMENT '支付方式',
`end_time` timestamp NULL DEFAULT NULL COMMENT '截止时间',
`add_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
`update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',
`tips` varchar(255) DEFAULT NULL COMMENT '固额码备注提示',
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='固额码记录表';</explode>

INSERT INTO `xy_cashier_core` VALUES
(NULL,'user_theme_data','{\"use\":\"festive_red\",\"list\":{\"young_blue\":{\"name\":\"\\u7a1a\\u5ae9\\u84dd\",\"bg\":\"#1678ff\",\"bgs\":\"#94ddff\",\"t\":\"#fff\"},\"classic_purple\":{\"name\":\"\\u7ecf\\u5178\\u7d2b\",\"bg\":\"#6e00ff\",\"bgs\":\"#e2ccff\",\"t\":\"#fff\"},\"soft_powder\":{\"name\":\"\\u6e29\\u67d4\\u7c89\",\"bg\":\"#ff576e\",\"bgs\":\"#f7c8d7\",\"t\":\"#fff\"},\"eye_protection_green\":{\"name\":\"\\u62a4\\u773c\\u7eff\",\"bg\":\"#0ec493\",\"bgs\":\"#b4ffeb\",\"t\":\"#fff\"},\"elegant_yellow\":{\"name\":\"\\u4f18\\u96c5\\u9ec4\",\"bg\":\"#ffa751\",\"bgs\":\"#fff4c0\",\"t\":\"#fff\"},\"festive_red\":{\"name\":\"\\u559c\\u5e86\\u7ea2\",\"bg\":\"#fe2424\",\"bgs\":\"#feafaf\",\"t\":\"#fff\"}}}','商户后台主题色数据')
,(NULL,'page_grey','','网站置灰');</explode>
