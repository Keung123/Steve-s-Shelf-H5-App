SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for ht_introduction
-- ----------------------------
DROP TABLE IF EXISTS `ht_introduction`;
CREATE TABLE `ht_introduction` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'id',
  `img` varchar(255) NOT NULL COMMENT '图片路径',
  `createtime` int(10) NOT NULL COMMENT '创建时间',
  `weigh` int(10) DEFAULT NULL COMMENT '排序权重',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COMMENT='引导页';