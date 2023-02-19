<?php
// require $_SERVER['DOCUMENT_ROOT'].'/wp-load.php';
require $_SERVER['DOCUMENT_ROOT'].'wp-content/themes/test-theme/inc/google-analytics-admin/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'wp-content/themes/test-theme/inc/google-analytics-api/vendor/autoload.php';


use Google\Analytics\Admin\V1alpha\AnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1alpha\PropertySummary;
use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\Filter;

// add credentials
putenv('GOOGLE_APPLICATION_CREDENTIALS='. THEME_URL .'/inc/credentials-new.json');

class GoogleAnalyticsCustomClass
{

    private $client;
    private $client_beta;
    
    public function __construct()
    {
        $this->client = new AnalyticsAdminServiceClient();
        $this->client_beta = new BetaAnalyticsDataClient();
        $this->accounts = $this->client->listAccountSummaries();
    }

    /*
     * Get all accounts
     * */
    public function google_get_all_analytics_accounts()
    {
        
        $accounts = $this->accounts;

        // get all analytics/properties ids
        foreach ($accounts as $account) {
            $summary = $account->getPropertySummaries();
            foreach ($summary as $sum) {
                $dealers_array[] = array(
                    'name' => preg_replace('/(^https:\/\/)|(\.haval-ukraine\.com)|(\/$)/', "", $sum->getDisplayName()),
                    'id' => str_replace('properties/', '', $sum->getProperty()),
                );
            }
        }

        // add dealers into db
        if(!empty($dealers_array)){
            global $wpdb;
            $dealers_data = json_encode($dealers_array);
            $sql_desc = "REPLACE INTO `analytics_general` (`name`, `data`) VALUES ('dealers', '$dealers_data' )";
            $res = $wpdb->query($sql_desc);
            if($wpdb->last_error !== ''){
                file_put_contents(__DIR__.'/log/error'.date('d.m.Y').'.txt', print_r($wpdb->last_result, true));
            }
        }

        return $dealers_array;
    }

    /*
     * Get general info from analytics by date
     * */
    public function google_general_request($start_date, $end_date){

        $accounts = $this->accounts;
        // Analytics call
        foreach ($accounts as $account) {
            $summary = $account->getPropertySummaries();
            foreach ($summary as $sum) {
                $client = $this->client_beta;
                $property = $sum->getProperty();
                $property_id = str_replace('properties/', '', $sum->getProperty());
                
                $response = $client->batchRunReports([
                    'property' => $property,
                    'requests' => [
                        // general data
                        new RunReportRequest([   
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]), // date change to save data only for tommorow
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']), // sort total users of site by date
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']),   // total users
                                new Metric(['name' => 'newUsers']), // uniq users
                                new Metric(['name' => 'screenPageViews']), // screen view
                                new Metric(['name' => 'averageSessionDuration']), // avarage time on site
                                new Metric(['name' => 'bounceRate']), // bounce
                            ]
                        ]),
                        // device category view
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'deviceCategory']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']),
                            ],
                            
                        ]),
                        // channel trafic
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'sessionDefaultChannelGroup']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'newUsers']),
                            ],
                            
                        ]),
                        // data per page
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'pagePath']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']), // total users
                                new Metric(['name' => 'bounceRate']), // bounce rate
                                new Metric(['name' => 'averageSessionDuration']), 
                            ],
                            'dimension_filter' => new FilterExpression([
                                'filter' => new Filter([
                                'field_name' => 'pagePath',
                                'string_filter' => new Filter\StringFilter([
                                    'value' => '/car/',
                                    'match_type' => Filter\StringFilter\MatchType::CONTAINS
                                    ]),
                                ]),
                            ])
                            
                        ]),
                        // data per page with filters
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'pagePath']),
                                new Dimension(['name' => 'deviceCategory']),
                                new Dimension(['name' => 'sessionDefaultChannelGroup']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']), // total users
                                new Metric(['name' => 'bounceRate']), // bounce rate
                                new Metric(['name' => 'averageSessionDuration']), 
                            ],
                            'dimension_filter' => new FilterExpression([
                                'filter' => new Filter([
                                'field_name' => 'pagePath',
                                'string_filter' => new Filter\StringFilter([
                                    'value' => '/car/',
                                    'match_type' => Filter\StringFilter\MatchType::CONTAINS
                                    ]),
                                ]),
                            ])
                        ]),

                    ]
                ]);

                $get_all_info[$property_id] = $this->google_analytics_normalize_responce($response, 1);
            }

            // second batch 
            foreach ($summary as $sum) {
                $client = $this->client_beta;
                $property = $sum->getProperty();
                $property_id = str_replace('properties/', '', $sum->getProperty());
                
                $response = $client->batchRunReports([
                    'property' => $property,
                    'requests' => [
                        // get info for filters
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'deviceCategory']),
                                new Dimension(['name' => 'sessionDefaultChannelGroup']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']),
                                new Metric(['name' => 'newUsers']),
                            ],
                        ]),
                        // converstions with filter
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'deviceCategory']),
                                new Dimension(['name' => 'sessionDefaultChannelGroup']),
                                new Dimension(['name' => 'eventName']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'eventCount']),
                            ],
                            'dimension_filter' => new FilterExpression([
                                'filter' => new Filter([
                                'field_name' => 'eventName',
                                'string_filter' => new Filter\StringFilter([
                                    'value' => 'GA4',
                                    'match_type' => Filter\StringFilter\MatchType::CONTAINS
                                    ]),
                                ]),
                            ])
                        ]),
                        // get events clicks
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'eventName']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'eventCount']),
                            ],
                            'dimension_filter' => new FilterExpression([
                                'filter' => new Filter([
                                'field_name' => 'eventName',
                                'string_filter' => new Filter\StringFilter([
                                    'value' => 'click',
                                    'match_type' => Filter\StringFilter\MatchType::CONTAINS
                                    ]),
                                ]),
                            ])
                        ]),
                        // total users per page
                        new RunReportRequest([
                            'property' => $property,
                            'date_ranges' => [
                                new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                            ],
                            'dimensions' => [
                                new Dimension(['name' => 'date']),
                                new Dimension(['name' => 'pagePath']),
                            ],
                            'metrics' => [
                                new Metric(['name' => 'activeUsers']), // total users
                            ],
                        ]),
                    ]
                ]);

                $filter_info[$property_id] = $this->google_analytics_normalize_responce($response, 2);
            }
        }

        $responce = array(
            'get_all_info' => $get_all_info,
            'filter_info' => $filter_info,
        );
        return $responce;
    }

    /*
     * Normalize google responce
     * */
    public function google_analytics_normalize_responce($response, $batch_number){
        foreach($response->getReports() as $key => $single_response){
            foreach ($single_response->getRows() as $row) {
                $date = date('Y-m-d', strtotime($row->getDimensionValues()[0]->getValue()));
                $totalUsers = $row->getMetricValues()[0]->getValue();
    
                if($batch_number == 1){
                    if($key == 0){
                        $responce_array[$date]['general_data'] = array(
                            'totalUsersAllpages' => $totalUsers,
                            'newUsers' => $row->getMetricValues()[1]->getValue(),
                            'screenPageView' => $row->getMetricValues()[2]->getValue(),
                            'averageSessionDuration' => $row->getMetricValues()[3]->getValue(),
                            'bounceRate' => $row->getMetricValues()[4]->getValue(),
                        );
                    }elseif($key == 1){
                        $deviceCategory = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $responce_array[$date]['general_data']['device_cat'][$deviceCategory] = $totalUsers;
                        // get all device categories
                    }elseif($key == 2){
                        $sesionChannel = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $responce_array[$date]['general_data']['session_channel'][$sesionChannel] = $totalUsers;
                        // get all sesions channels
                    }elseif($key == 3){
                        $page = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $bounceRate = $row->getMetricValues()[1]->getValue();
                        $averageTime = $row->getMetricValues()[2]->getValue();
                        $totalUsers = $row->getMetricValues()[0]->getValue();
                        $totalUsersSite = $responce_array[$date]['general_data']['totalUsersAllpages'];
                        
                        // hardcode fix after the slug is fixed 
                        $page = str_replace('haval-h6-tretogo-pokolinnya', 'haval-h6-l2', $page);
                        $page = preg_replace('/(\/car\/)|(\/$)/', "", $page);
    
                        $cvr = $totalUsersSite != 0 ? number_format(($totalUsers * 100) / $totalUsersSite, 2) : 0;
                        $page_info = array(
                            'totalSumm' => $totalUsersSite,
                            'totalUsers' => $totalUsers,
                            'CVR' => $cvr,
                            'exit' => '',
                            'bounceRate' => number_format($bounceRate, 2),
                            'userEngagementDuration' => $averageTime,
                        );
                        $responce_array[$date]['page_info'][$page] = $page_info;                   
    
                    }elseif($key == 4){
                        $page = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $dev_cat = ($row->getDimensionValues()[2] != null) ? $row->getDimensionValues()[2]->getValue() : '';
                        $sesionChannel = ($row->getDimensionValues()[3] != null) ? $row->getDimensionValues()[3]->getValue() : '';
                        $bounceRate = $row->getMetricValues()[1]->getValue();
                        $averageTime = $row->getMetricValues()[2]->getValue();
                        $totalUsers = $row->getMetricValues()[0]->getValue();
                        $totalUsersSite = $responce_array[$date]['general_data']['totalUsersAllpages'];
    
                        // hardcode fix after the slug is fixed 
                        $page = str_replace('haval-h6-tretogo-pokolinnya', 'haval-h6-l2', $page);
                        $page = preg_replace('/(\/car\/)|(\/$)/', "", $page);
    
                        $cvr = $totalUsersSite != 0 ? number_format(($totalUsers * 100) / $totalUsersSite, 2) : 0;
                        $page_info = array(
                            'totalUsers' => $totalUsers,
                            'CVR' => $cvr,
                            'bounceRate' => number_format($bounceRate, 2),
                            'userEngagementDuration' => $averageTime,
                        );
    
                        $responce_array[$date]['page_info'][$page]['device_cat'][$dev_cat][$sesionChannel] = $page_info;
    
                    }
                }
    
                if($batch_number == 2){
                    if($key == 0){
                        $deviceCategory = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $sesionChannel = ($row->getDimensionValues()[2] != null) ? $row->getDimensionValues()[2]->getValue() : '';
                        $newUsers = ($row->getMetricValues()[1]->getValue() != null) ? $row->getMetricValues()[1]->getValue() : '';
                        $responce_array[$date]['filter_general']['totalUsersAllpages']['device_cat'][$deviceCategory][$sesionChannel] = $totalUsers;
                        $responce_array[$date]['filter_general']['newUsers']['device_cat'][$deviceCategory][$sesionChannel] = $newUsers;
                    }elseif($key == 1){
                        $deviceCategory = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
                        $sesionChannel = ($row->getDimensionValues()[2] != null) ? $row->getDimensionValues()[2]->getValue() : '';
                        $eventName = ($row->getDimensionValues()[3] != null) ? $row->getDimensionValues()[3]->getValue() : '';
                        $eventCount = ($row->getMetricValues()[0]->getValue() != null) ? $row->getMetricValues()[0]->getValue() : '';
    
                        $responce_array[$date]['convertion_info']['device_cat'][$deviceCategory][$sesionChannel][$eventName] = $eventCount;
                    }elseif($key == 2){
                        $eventCount = ($row->getMetricValues()[0]->getValue() != null) ? $row->getMetricValues()[0]->getValue() : '';
    
                        $responce_array[$date]['filter_general']['totalClicks'] = $eventCount;
                    }elseif($key == 3){
                        $totalUsers = ($row->getMetricValues()[0]->getValue() != null) ? $row->getMetricValues()[0]->getValue() : '';
                        $page = ($row->getDimensionValues()[1] != null) ? $row->getDimensionValues()[1]->getValue() : '';
    
                        $responce_array[$date]['total_users_by_page'][$page] = $totalUsers;
                    }
                }
    
            }
        }
    
        return $responce_array;
    }


     /*
     * Google analytics additional info for total users 
     * */
    public function google_analytics_additional_info($start_date, $end_date, $dealer_id){
        $responce_array = [];
        $property = 'properties/'.$dealer_id;
        $client = $this->client_beta;
        $response = $client->batchRunReports([
            'property' => $property,
            'requests' => [
                // dev category info
                new RunReportRequest([
                    'property' => $property,
                    'date_ranges' => [
                        new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                    ],
                    'dimensions' => [
                        new Dimension(['name' => 'deviceCategory']),
                    ],
                    'metrics' => [
                        new Metric(['name' => 'activeUsers']),
                    ],
                ]),
                // total and uniq users
                new RunReportRequest([
                    'property' => $property,
                    'date_ranges' => [
                        new DateRange(['start_date' => $start_date, 'end_date' => $end_date]),
                    ],
                    'metrics' => [
                        new Metric(['name' => 'activeUsers']),
                        new Metric(['name' => 'newUsers']),
                    ],
                ]),
            ]
        ]);

        foreach($response->getReports() as $key => $single_response){
            foreach($single_response->getRows() as $row) {
                // get 
                if($key == 0){
                    $totalUsers = $row->getMetricValues()[0]->getValue();
                    $dev_cat = $row->getDimensionValues()[0]->getValue();
                    $responce_array[$dev_cat] = $totalUsers;
                }elseif($key == 1){
                    $responce_array['activeUsers'] = $row->getMetricValues()[0]->getValue();
                    $responce_array['newUsers'] = $row->getMetricValues()[1]->getValue();
                }
               
            }
        }
        
        $responce = array(
            'devCat' => array(
                'desktop' => $responce_array['desktop'],
                'tablet' => $responce_array['tablet'],
                'mobile' => $responce_array['mobile'],
            ),
            'totalUsers' => array(
                'totalUsers' => $responce_array['activeUsers'],
                'uniqUsers' => $responce_array['newUsers'],
            )
        );

        return $responce;
    }



}

new GoogleAnalyticsCustomClass();