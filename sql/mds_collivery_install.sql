CREATE TABLE IF NOT EXISTS `#__mds_collivery_config` (
                                                         `id` int(11) NOT NULL AUTO_INCREMENT,
                                                         `password` varchar(55) NOT NULL,
                                                         `username` varchar(55) NOT NULL,
                                                         `risk_cover` int(1) NOT NULL,
                                                         PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `#__mds_collivery_config` (`password`, `username`, `risk_cover`) VALUES
    ('api123', 'api@collivery.co.za', 1);

INSERT INTO `#__extensions` (`name`, `type`, `element`, `folder`, `enabled`, `access`, `protected`, `manifest_cache`) VALUES
    ('MDS Collivery Custom Validation', 'plugin', 'mds_validation', 'vmuserfield', 1, 1, 1, '{"legacy":false,"name":"MDS Collivery Custom Validation","type":"plugin","creationDate":"2014-03-04","author":"MDS Collivery","copyright":"Copyright (C) 2014. MDS Collivery All rights reserved.","authorEmail":"integration@collivery.co.za","authorUrl":"http:\\/\\/www.collivery.co.za","version":"1","description":"Includes Jquery validation that extends virtuemart","group":""}');

CREATE TABLE IF NOT EXISTS `#__mds_collivery_processed` (
                                                            `id` int(11) NOT NULL AUTO_INCREMENT,
                                                            `waybill` int(11) NOT NULL,
                                                            `validation_results` TEXT NOT NULL,
                                                            `status` int(1) NOT NULL DEFAULT 1,
                                                            PRIMARY KEY (`id`)
) ENGINE=InnoDB;

INSERT INTO `#__menu` (`menutype`, `title`, `alias`, `note`, `path`, `link`, `type`, `published`, `parent_id`, `level`, `component_id`, `checked_out`, `checked_out_time`, `browserNav`, `access`, `img`, `template_style_id`, `params`, `lft`, `rgt`, `home`, `language`, `client_id`) VALUES ('mainmenu', 'MDS Tracking', 'mds-tracking', '', 'mds-tracking', 'index.php?option=com_virtuemart&view=mds&task=tracking', 'component', '1', '1', '1', '0', '0', '0000-00-00 00:00:00.000000', '0', '1', '', '0', '{"show_noauth":"","show_title":"","link_titles":"","show_intro":"","show_category":"0","link_category":"","show_parent_category":"0","link_parent_category":"","show_author":"0","link_author":"","show_create_date":"0","show_modify_date":"0","show_publish_date":"0","show_item_navigation":"0","show_icons":"","show_print_icon":"","show_email_icon":"","show_hits":"0","robots":"","rights":"","menu-anchor_title":"","menu-anchor_css":"","menu_image":"","show_page_heading":0,"page_title":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","menu-meta_keywords":"","secure":0}', '267', '272', '0', '*', '0');

CREATE TABLE IF NOT EXISTS `#__virtuemart_shipment_plg_mds_shipping` (
                                                                         `id` int(11) NOT NULL AUTO_INCREMENT,
                                                                         `virtuemart_order_id` int(11) NOT NULL,
                                                                         `order_number` char(32) NOT NULL,
                                                                         `virtuemart_shipmentmethod_id` int(11) NOT NULL,
                                                                         `shipment_name` varchar(5000) NOT NULL,
                                                                         `rule_name` varchar(500) NOT NULL,
                                                                         `order_weight` decimal(10,6) NOT NULL,
                                                                         `order_products` int(11) NOT NULL,
                                                                         `shipment_cost` decimal(10,2) NOT NULL,
                                                                         `created_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                                                                         `created_by` int(11) NOT NULL DEFAULT '0',
                                                                         `modified_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                                                                         `modified_by` int(11) NOT NULL DEFAULT '0',
                                                                         `locked_on` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
                                                                         `locked_by` int(11) NOT NULL DEFAULT '0',
                                                                         PRIMARY KEY (`id`)
) ENGINE=InnoDB;

ALTER TABLE `#__virtuemart_order_userinfos` ADD `mds_suburb_id` int(11) NOT NULL  AFTER `agreed`,  ADD `mds_building` VARCHAR(255) NOT NULL  AFTER `mds_suburb_id`,  ADD `mds_location_type` int(11) NOT NULL  AFTER `mds_building`;
ALTER TABLE `#__virtuemart_userinfos` ADD `mds_suburb_id` int(11) NOT NULL  AFTER `zip`,  ADD `mds_building` VARCHAR(255) NOT NULL  AFTER `mds_suburb_id`,  ADD `mds_location_type` int(11) NOT NULL  AFTER `mds_building`;
