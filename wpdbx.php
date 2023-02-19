<?php
// add class to extend wpdb
class wpdbx extends wpdb {
    public function __construct() {
        parent::__construct(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
    }

    public function insert_multiple($table, $data) {
        $this->insert_id = 0;
        $format = null;

        // check if table exists, create table if not
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        if (str_contains($table, 'google_analytics')) {
        $create_table_query = "
                CREATE TABLE IF NOT EXISTS `{$table}` (
                  `day` date NOT NULL,
                  `total_users_by_page` json NOT NULL,
                  `general_info` json NOT NULL,
                  `page_info` json NOT NULL,
                  `filter_general` json NOT NULL,
                  `convertion_info` json NOT NULL,
                  `total_by_page` json NOT NULL,
                  PRIMARY KEY  (day)
                ) {$charset_collate};
        ";
        }else if(str_contains($table, 'analytics_users')){
            $create_table_query = "
                CREATE TABLE IF NOT EXISTS `{$table}` (
                  `date` date NOT NULL,
                  `user_id` varchar(255) NOT NULL,
                  `device_cat` varchar(255) NOT NULL,
                  `source` varchar(255) NOT NULL,
                  `referal` varchar(255) NOT NULL,
                  `engagement_time_seconds` varchar(255) NOT NULL,
                  `page_journey` json NOT NULL,
                  `page_time` json NOT NULL,
                  PRIMARY KEY  (user_id)
                ) {$charset_collate};
        ";
        }
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $create_table_query );
        $is_error = empty( $wpdb->last_error );
        if($is_error){
            file_put_contents(__DIR__.'/log/error_log'. date('d.m.YHis') .'.txt', print_r($wpdb->last_error, true));
        }

        // check if there all columns in table
        // foreach($models as $single_model){
        // $row = $wpdb->get_results(  "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '{$table}' AND column_name = 'engagement_time_seconds'"  );
        // if(empty($row)){
        //     $wpdb->query("ALTER TABLE `{$table}` ADD `engagement_time_seconds` varchar(255) NOT NULL");
        // }
        // }
        
       
        if(count($data) > 1000){
            // chunk array
            $arrray_chunk = array_chunk($data, 1000);
            
            foreach($arrray_chunk as $single_data){
                $formats = array();
                $values = array();
                foreach ($single_data as $index => $row) {
                    $row = $this->process_fields($table, $row, $format);
                    $row_formats = array();
    
                    if ($row === false || array_keys($single_data[$index]) !== array_keys($single_data[0])) {
                        continue;
                    }
    
                    foreach($row as $col => $value) {
                        if (is_null($value['value'])) {
                            $row_formats[] = 'NULL';
                        } else {
                            $row_formats[] = $value['format'];
                        }
    
                        $values[] = $value['value'];
                    }
                    $formats[] = '(' . implode(', ', $row_formats) . ')';
                }

                $fields  = '`' . implode('`, `', array_keys($single_data[0])) . '`';
                $formats = implode(', ', $formats);
        
                $sql = "REPLACE INTO `$table` ($fields) VALUES $formats";
        
                $this->check_current_query = false;
                if($this->query($this->prepare($sql, $values)) == false){
                    file_put_contents(__DIR__.'/log/error_log'. date('d.m.YHis') .'.txt', $wpdb->print_error());
                    return false;
                }else{
                    $this->query($this->prepare($sql, $values));
                }               
            }
            return true;
        }else{
            $formats = array();
            $values = array();
            foreach ($data as $index => $row) {
                $row = $this->process_fields($table, $row, $format);
                $row_formats = array();

                if ($row === false || array_keys($data[$index]) !== array_keys($data[0])) {
                    continue;
                }

                foreach($row as $col => $value) {
                    if (is_null($value['value'])) {
                    $row_formats[] = 'NULL';
                    } else {
                    $row_formats[] = $value['format'];
                    }

                    $values[] = $value['value'];
                }
                $formats[] = '(' . implode(', ', $row_formats) . ')';
            }
            $fields  = '`' . implode('`, `', array_keys($data[0])) . '`';
            $formats = implode(', ', $formats);
    
            $sql = "REPLACE INTO `$table` ($fields) VALUES $formats";
    
            $this->check_current_query = false;
            return $this->query($this->prepare($sql, $values));
        }
            

    }
}
  
global $wpdbx;
$wpdbx = new wpdbx();
