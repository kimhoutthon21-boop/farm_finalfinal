-- Initial configuration only. No fake reports, no fake finances, no default users.
INSERT IGNORE INTO houses (house_number, status) VALUES (1,'empty'),(2,'empty'),(3,'empty'),(4,'empty');

INSERT IGNORE INTO farm_settings (setting_key, setting_value) VALUES
 ('farm_name','Sethy''s chicken farm'),
 ('farm_info',''),
 ('currency_code','KHR'),
 ('currency_symbol','៛'),
 ('currency_decimals','0'),
 ('default_cycle_days','70'),
 ('bag_weight_kg',''),
 ('allow_partial_bags','1'),
 ('worker_backdate_days','2'),
 ('report_reminder_hour','17'),
 ('daily_mortality_warn_pct','0.20'),
 ('cycle_mortality_warn_pct','5.00');

INSERT IGNORE INTO death_causes (name, requires_detail, sort_order) VALUES
 ('Disease',0,1),('Weak chicks',0,2),('Injury',0,3),('Environmental conditions',0,4),('Unknown',0,5),('Other',1,6);

INSERT IGNORE INTO work_task_categories (name, sort_order) VALUES
 ('Cleaning',1),('Feeding',2),('Equipment maintenance',3),('Litter management',4),('General house maintenance',5),('Removing dead birds',6),('Checking drinkers and feeders',7);

INSERT IGNORE INTO finance_categories (type, name) VALUES
 ('expense','Feed purchases'),('expense','Chicks'),('expense','Labor'),('expense','Electricity'),('expense','Medicine'),
 ('expense','Transportation'),('expense','Maintenance'),('expense','Miscellaneous'),
 ('revenue','Sales revenue'),('revenue','Other farm income');
