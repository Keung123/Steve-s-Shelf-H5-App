SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for ht_introduction
-- ----------------------------
DROP TABLE IF EXISTS `ht_coupon`;
CREATE TABLE `ht_coupon` (
  `coupon_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '优惠券id',
  `coupon_title` varchar(100) NOT NULL COMMENT '优惠券名称',
  `coupon_thumb` varchar(255) DEFAULT NULL COMMENT '优惠券封面',
  `coupon_type_id` int(10) NOT NULL DEFAULT '0' COMMENT '商品券为商品id，专区卷为活动专区id',
  `coupon_type` tinyint(1) NOT NULL COMMENT '优惠券类型：1，商品券；2，专区券；3，全场券',
  `disabled` varchar(100) NOT NULL COMMENT '不可用分类id',
  `coupon_price` float(10,2) NOT NULL COMMENT '优惠券面额',
  `coupon_use_limit` float(10,2) NOT NULL COMMENT '优惠券满减的门槛，0为无门槛',
  `coupon_get_limit` int(10) DEFAULT NULL COMMENT '每人限领张数',
  `coupon_s_time` int(10) NOT NULL COMMENT '优惠券生效时间',
  `coupon_aval_time` int(10) NOT NULL COMMENT '优惠券到期时间',
  `coupon_total` int(10) NOT NULL COMMENT '优惠券总张数',
  `coupon_add_time` int(10) DEFAULT NULL COMMENT '优惠券增加时间',
  `coupon_stat` tinyint(1) NOT NULL DEFAULT '0' COMMENT '优惠券模板状态：0，有效；1，失效',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '优惠券状态：0，正常；1，删除',
  PRIMARY KEY (`coupon_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='优惠券表';



DROP TABLE IF EXISTS `ht_coupon_users`;
CREATE TABLE `ht_coupon_users` (
  `c_id` int(10) NOT NULL AUTO_INCREMENT COMMENT '自增id',
  `coupon_id` int(10) NOT NULL COMMENT '优惠券id',
  `c_uid` int(10) NOT NULL COMMENT '优惠券所属用户',
  `add_time` int(100) NOT NULL COMMENT '领取时间',
  `coupon_stat` tinyint(1) DEFAULT '1' COMMENT '优惠券状态：1，未使用；2，已使用；3，已过期；4，已转赠',
  `c_coupon_title` varchar(100) NOT NULL COMMENT '代金券名称',
  `c_coupon_type` tinyint(1) NOT NULL COMMENT '代金券类型:1，商品券；2，专区券；3，全场券',
  `c_coupon_price` float(10,2) NOT NULL COMMENT '代金券面额',
  `c_coupon_buy_price` float(10,2) NOT NULL COMMENT '代金券使用条件',
  `coupon_type_id` int(10) NOT NULL DEFAULT '0' COMMENT '商品券为商品id，专区卷为活动专区id',
  `coupon_aval_time` int(10) NOT NULL DEFAULT '0' COMMENT '优惠券到期时间',
  `update_time` int(10) DEFAULT NULL COMMENT '使用时间',
  `c_no` varchar(50) DEFAULT NULL COMMENT '优惠券编号（购买时填入）',
  `c_coupon_thumb` varchar(100) DEFAULT NULL COMMENT '优惠券封面',
  PRIMARY KEY (`c_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='用户优惠券表';
