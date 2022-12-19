#星益云www.96xy.cn【1019数据包】;</explode>

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
