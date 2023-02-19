<?php
add_action('rest_api_init', function () {
// CORS eroor fixed
    add_filter('rest_pre_serve_request', function ($value) {
        header('Access-Control-Allow-Headers: Authorization, X-WP-Nonce, Content-Type, X-Requested-With');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Credentials: true');

        return $value;
    });

    // general route
    register_rest_route('analytics/v1', 'general', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'analytics_get_general_results',
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // general table route
    register_rest_route('analytics/v1', 'tablegeneral', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'get_table_general_info',
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // get deallers ids
    register_rest_route('analytics/v1', 'dealers', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            
            $data = get_all_deallers();
            return $data;
            
        },
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // get models
    register_rest_route('analytics/v1', 'models', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {

            $data = get_models();
            return $data;

        },
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // get device cat
    register_rest_route('analytics/v1', 'devcat', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            
            $data = '';
            global $wpdb;

            // get dev categories
            $sql = "SELECT data FROM analytics_general WHERE name = 'devcategories'";
            $devCat = $wpdb->get_results($sql, ARRAY_A);
            $data = json_decode($devCat[0]['data']);

            return $data;
            
        },
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // get sesions channels
    register_rest_route('analytics/v1', 'sesionchannel', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            
            $data = '';
            global $wpdb;

            // get sesions channels
            $sql = "SELECT data FROM analytics_general WHERE name = 'sessionchannel'";
            $sessionChan = $wpdb->get_results($sql, ARRAY_A);
            $data = json_decode($sessionChan[0]['data']);

            return $data;
            
        },
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // Total / exits grahic
    register_rest_route('analytics/v1', 'totalexitsgrahic', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'get_total_exits_grahic',
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // Users 
    register_rest_route('analytics/v1', 'users', array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'get_total_users',
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    // Export users route
    register_rest_route('analytics/v1', 'export_users', array(
        'methods' => WP_REST_Server::CREATABLE,
        // 'methods' => WP_REST_Server::READABLE,
        'callback' => 'export_users',
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));
  
    function analytics_get_general_results(WP_REST_Request $request){
        //get date data
        $first_date = $request->get_param('dateFrom');
        $last_date = (!empty($request->get_param('dateTo')) && $request->get_param('dateTo') != '1970-01-1') ? $request->get_param('dateTo') : $first_date;
        // get dealer id (use dealer id to gain info from correct database)
        $dealer_id = $request->get_param('dealers');
        
        $pages_info = get_general_info_for_dates($first_date, $last_date, $dealer_id);

        $totalUsers = 0;
        $timeOnSiteSumm = [];
        if(!empty($pages_info)){
            
            $responce_array = generate_page_info_responce($pages_info, $first_date, $last_date, $dealer_id);

            return $responce_array;
        }else{

            $result = array(
                'code' => 'info_not_found',
                'message' => __('Looks like there is no results for this dates, please try another periods', 'analytics'),
                'data' => array(
                    'status' => 404
                )
            );

            return $result;
        }
    }

    function generate_page_info_responce($pages_info, $first_date, $last_date, $single_dealer = null){
        $unnasigned = $organicSoc = $organicSearch = $crossNetwork = $paidSearch = $display = $direct = $referral = $paidVideo = $desktopCat = $tabletCat = $mobileCat = $totalUsersSumm = $screenPageViewSumm = $uniqUsersSumm = $totalClicksSumm = 0;

        // get custom events for single dealer
        if($single_dealer != null){
            $analytics_info = new GoogleAnalyticsCustomClass();
            $info = $analytics_info->google_analytics_additional_info($first_date, $last_date, $single_dealer);
            $deviceCatGen = $info['devCat'];
            $totalUsersSumm = $info['totalUsers']['totalUsers'];
            $uniqUsersSumm = $info['totalUsers']['uniqUsers'];
        }

        foreach($pages_info as $date => $single_date_data){
            
            $screenPageViewSumm += $single_date_data['general_data']['screenPageView'];
            $totalClicksSumm += $single_date_data['general_data']['totalClicks'];
            
            setlocale(LC_TIME, 'uk_UA.utf8');
            // total / uniq users
            $totalUsersByDay[] = array(
                'label' => strftime('%d %b', strtotime($date)),
                'data' => $single_date_data['general_data']['totalUsersAllpages'],
            );
            $totalUUsersByDay[] = array(
                'label' => strftime('%d %b', strtotime($date)),
                'data' => $single_date_data['general_data']['newUsers'],
            );

            // devices traffic
            // for single dealer create custom request to google analytics
            if($single_dealer == null){
                $totalUsersSumm += $single_date_data['general_data']['totalUsersAllpages'];
                $uniqUsersSumm += $single_date_data['general_data']['newUsers'];
                $deviceCatGen = array(
                    'desktop' => !empty($single_date_data['general_data']['device_cat']['desktop']) ? $desktopCat += $single_date_data['general_data']['device_cat']['desktop'] : $desktopCat,
                    'tablet' => !empty($single_date_data['general_data']['device_cat']['tablet']) ? $tabletCat += $single_date_data['general_data']['device_cat']['tablet'] : $tabletCat,
                    'mobile' => !empty($single_date_data['general_data']['device_cat']['mobile']) ? $mobileCat += $single_date_data['general_data']['device_cat']['mobile'] : $mobileCat,
                );
            }
            
            // trafic channel
            $traficChannel = array(
                'unassigned' => !empty($single_date_data['general_data']['session_channel']['Unassigned']) ? $unnasigned += $single_date_data['general_data']['session_channel']['Unassigned'] : $unnasigned,
                'organic-social' => !empty($single_date_data['general_data']['session_channel']['Organic Social']) ? $organicSoc += $single_date_data['general_data']['session_channel']['Organic Social'] : $organicSoc,
                'organic-search' => !empty($single_date_data['general_data']['session_channel']['Organic Search']) ? $organicSearch += $single_date_data['general_data']['session_channel']['Organic Search'] : $organicSearch,
                'cross-network' => !empty($single_date_data['general_data']['session_channel']['Cross-network']) ? $crossNetwork += $single_date_data['general_data']['session_channel']['Cross-network'] : $crossNetwork,
                'paid-search' => !empty($single_date_data['general_data']['session_channel']['Paid Search']) ? $paidSearch += $single_date_data['general_data']['session_channel']['Paid Search'] : $paidSearch,
                'display' => !empty($single_date_data['general_data']['session_channel']['Display']) ? $display += $single_date_data['general_data']['session_channel']['Display'] : $display,
                'direct' => !empty($single_date_data['general_data']['session_channel']['Direct']) ? $direct += $single_date_data['general_data']['session_channel']['Direct'] : $direct,
                'referral' => !empty($single_date_data['general_data']['session_channel']['Referral']) ? $referral += $single_date_data['general_data']['session_channel']['Referral'] : $referral,
                'paid_video' => !empty($single_date_data['general_data']['session_channel']['Paid Video']) ? $paidVideo += $single_date_data['general_data']['session_channel']['Paid Video'] : $paidVideo,
            );

            // content overview
            if(is_array($single_date_data['general_data']['bounceRate'])){
                $averageBounceRate = get_average($single_date_data['general_data']['bounceRate']);
                $averageBounceRateSumm[] = $averageBounceRate;
            }else{
                $averageBounceRate = $single_date_data['general_data']['bounceRate'];
                $averageBounceRateSumm[] = $single_date_data['general_data']['bounceRate'];
            }
            $bounceRateArray[] = array(
                'label' => strftime('%d %b', strtotime($date)),
                'data' => number_format($averageBounceRate, 2),
            );

            // time on site
            if(is_array($single_date_data['general_data']['averageSessionDuration'])){
                $averageTime = get_average($single_date_data['general_data']['averageSessionDuration']);
                $timeOnSiteSumm[] = $averageTime;
            }else{
                $averageTime = $single_date_data['general_data']['averageSessionDuration'];
                $timeOnSiteSumm[] = $single_date_data['general_data']['averageSessionDuration'];
            }
            $timeOnSite[] = array(
                'label' => strftime('%d %b', strtotime($date)),
                'data' => round($averageTime, 0),
            );
            
        }

        // all users table 
        $grafic_1 = [$totalUsersByDay, $totalUUsersByDay];

        // trafic channel
        $devicesTotal = array_sum($deviceCatGen);

        $deviceCatGen = array_map(function($hits) use ($devicesTotal) {
            $calc = round($hits / $devicesTotal * 100, 2);
            return sprintf('%.2f', $calc);
        }, $deviceCatGen);

        foreach($deviceCatGen as $label => $singleData){
            $name = device_cat_rename($label);
            $totalDeviceCat[] = array(
                'label' => $name,
                'data' => $singleData,
            );
        }
    
        // trafic channel
        foreach($traficChannel as $label => $single_chennel){
            switch ($label) {
                case 'unassigned':
                    $name = __('Unassigned', 'analytics');
                    break;
                case 'organic-social':
                    $name = __('Organic Social', 'analytics');
                    break;
                case 'organic-search':
                    $name = __('Organic Search', 'analytics');
                    break;
                case 'cross-network':
                    $name = __('Cross-network', 'analytics');
                    break;
                case 'paid-search':
                    $name = __('Paid Search', 'analytics');
                    break;
                case 'display':
                    $name = __('Display', 'analytics');
                    break;
                case 'direct':
                    $name = __('Direct', 'analytics');
                    break;
                case 'referral':
                    $name = __('Referral', 'analytics');
                    break;
                case 'paid_video':
                    $name = __('Paid Video', 'analytics');
                    break;
            }
            $totalTraficChannel[] = array(
                'label' => $name,
                'data' => $single_chennel,
            );
        }

        // average time on site
        $timeOnSiteArray = array(
            'averageData' => round(get_average($timeOnSiteSumm), 0),
            'chartPoints' => $timeOnSite,
        );

        // content overview
        $contentOverview = array(
            array(
                'averageData' => round(get_average($timeOnSiteSumm), 0),
                'chartPoints' => $timeOnSite,
            ),
            array(
                'averageData' => number_format((number_format((float)get_average($averageBounceRateSumm), 2) * 100), 0) ,
                'chartPoints' => $bounceRateArray,
            ),
        );

        $responce_array = array(
            'totalUsers' => $totalUsersSumm,
            'uniqUsers' => $uniqUsersSumm,
            'screenPageView' => $screenPageViewSumm,
            'clicks' => $totalClicksSumm,
            'deviceCat' => $totalDeviceCat,
            'traficChannel' => $totalTraficChannel,
            'allUsers' => $grafic_1,
            'timeOnSite' => $timeOnSiteArray,
            'contentOverview' => $contentOverview,
        );
        return $responce_array;
    }


    function get_general_info_for_dates($first_date, $last_date, $dealers_id = null){
        
        global $wpdb;
        
        if($dealers_id == null){
            $all_dealers_info = get_all_deallers();
            $general_array = $array_sum = $test = [];

            // get info from all of the dealers
            foreach($all_dealers_info as $single_dealer){
                // $single_dealer_id = $single_dealer['id'];
                $sql = $wpdb->prepare("SELECT * FROM google_analytics_{$single_dealer['id']} WHERE day between '$first_date' AND '$last_date'", ARRAY_A);
                $results = $wpdb->get_results($sql, ARRAY_A);
                //unserialize data
                $results_array = [];
                foreach($results as $single_date){
                    $results_array[$single_date['day']] = array(
                        'general_data' => json_decode($single_date['general_info'], true),
                        'filter_general' => json_decode($single_date['filter_general'], true),
                    );
                    $dates[] = $single_date['day'];
                }
                // $general_array[] = $results_array;
                $general_array[$single_dealer['id']] = $results_array;
            }

            // check if there is results in database
            if(empty($results_array)) return false; 
            
            // remove duplicate dates
            $dates_new = array_unique($dates);
            // summ dealers info by day
            file_put_contents(__DIR__.'/A_request.txt', print_r($general_array, true));
            foreach($dates_new as $single_date){
                $newUsers = $mobile = $tablet = $desktop = $totalClicks = $screenPageView = $totalUsersAllpages = $direct = $display = $referral = $paidVideo = $paidSearch = $paidSocial = $crossNetwork = $organicSearch = $organicSocial = 0;
                $array_sum[$single_date]['general_data']['averageSessionDuration'] = $array_sum[$single_date]['general_data']['bounceRate'] = [];
                
                    foreach($general_array as $key => $single_result){
                        if(!isset($single_result[$single_date]['general_data'])) continue;
                        
                        $array_sum[$single_date] = array(
                            'general_data' => array(
                                'newUsers' => isset($single_result[$single_date]['general_data']['newUsers']) ? $newUsers += $single_result[$single_date]['general_data']['newUsers'] : $newUsers,
                                'device_cat' => array(
                                    'mobile' => isset($single_result[$single_date]['general_data']['device_cat']['mobile']) ? $mobile += $single_result[$single_date]['general_data']['device_cat']['mobile'] : $mobile,
                                    'tablet' => isset($single_result[$single_date]['general_data']['device_cat']['tablet']) ? $tablet += $single_result[$single_date]['general_data']['device_cat']['tablet'] : $tablet,
                                    'desktop' => isset($single_result[$single_date]['general_data']['device_cat']['desktop']) ? $desktop += $single_result[$single_date]['general_data']['device_cat']['desktop'] : $desktop,
                                ),
                                'totalClicks' => isset($single_result[$single_date]['filter_general']['totalClicks']) ? $totalClicks += $single_result[$single_date]['filter_general']['totalClicks'] : $totalClicks,
                                'screenPageView' => isset($single_result[$single_date]['general_data']['screenPageView']) ? $screenPageView += $single_result[$single_date]['general_data']['screenPageView'] : $screenPageView,
                                'totalUsersAllpages' => isset($single_result[$single_date]['general_data']['totalUsersAllpages']) ? $totalUsersAllpages += $single_result[$single_date]['general_data']['totalUsersAllpages'] : $totalUsersAllpages,
                                'session_channel' => array(
                                    'Direct' => isset($single_result[$single_date]['general_data']['session_channel']['Direct']) ? $direct += $single_result[$single_date]['general_data']['session_channel']['Direct'] : $direct,
                                    'Display' => isset($single_result[$single_date]['general_data']['session_channel']['Display']) ? $display += $single_result[$single_date]['general_data']['session_channel']['Display'] : $display,
                                    'Referral' => isset($single_result[$single_date]['general_data']['session_channel']['Referral']) ? $referral += $single_result[$single_date]['general_data']['session_channel']['Referral'] : $referral,
                                    'Paid Video' => isset($single_result[$single_date]['general_data']['session_channel']['Paid Video']) ? $paidVideo += $single_result[$single_date]['general_data']['session_channel']['Paid Video'] : $paidVideo,
                                    'Paid Search' => isset($single_result[$single_date]['general_data']['session_channel']['Paid Search']) ? $paidSearch += $single_result[$single_date]['general_data']['session_channel']['Paid Search'] : $paidSearch,
                                    'Paid Social' => isset($single_result[$single_date]['general_data']['session_channel']['Paid Social']) ? $paidSocial += $single_result[$single_date]['general_data']['session_channel']['Paid Social'] : $paidSocial,
                                    'Cross-network' => isset($single_result[$single_date]['general_data']['session_channel']['Cross-network']) ? $crossNetwork += $single_result[$single_date]['general_data']['session_channel']['Cross-network'] : $crossNetwork,
                                    'Organic Search' => isset($single_result[$single_date]['general_data']['session_channel']['Organic Search']) ? $organicSearch += $single_result[$single_date]['general_data']['session_channel']['Organic Search'] : $organicSearch,
                                    'Organic Social' => isset($single_result[$single_date]['general_data']['session_channel']['Organic Social']) ? $organicSocial += $single_result[$single_date]['general_data']['session_channel']['Organic Social'] : $organicSocial,
                                )
                            )
                        );
                    // average time
                    $array_sum[$single_date]['general_data']['averageSessionDuration'][] = $single_result[$single_date]['general_data']['averageSessionDuration'];
                    // bounce rate
                    $array_sum[$single_date]['general_data']['bounceRate'][] = $single_result[$single_date]['general_data']['bounceRate'];

                }
            }

            $results_array = $array_sum;
        }else{
            $sql = $wpdb->prepare("SELECT * FROM google_analytics_{$dealers_id} WHERE day between '$first_date' AND '$last_date'", ARRAY_A);
            $results = $wpdb->get_results($sql, ARRAY_A);
            //unserialize data
            foreach($results as $single_date){

                // single general info
                $general = json_decode($single_date['general_info'], true);
                $total_clicks = json_decode($single_date['filter_general'], true);
                $general['totalClicks'] = isset($total_clicks['totalClicks']) ? $total_clicks['totalClicks'] : 0;
                $results_array[$single_date['day']] = array(
                    'general_data' => $general,
                );
            }

            // check if there is results in database
            if(empty($results_array)) return false; 

        }

        return $results_array;
    }


    // table page
    function get_table_general_info(WP_REST_Request $request){

        $first_date = $request->get_param('dateFrom');
        $last_date = (!empty($request->get_param('dateTo')) && $request->get_param('dateTo') != '1970-01-1') ? $request->get_param('dateTo') : $first_date;

        $sesionsChannels    = !empty($request->get_param('channels')) ? $request->get_param('channels') : '';
        $deviceCat          = !empty($request->get_param('devices')) ? $request->get_param('devices') : '';

        // get all deallers ids
        $dealers = get_all_deallers();
        $dealer_info = [];
        // get info from db
        foreach($dealers as $single_dealer){
            $pages_info = get_table_info_by_model($first_date, $last_date, $single_dealer['id'], $deviceCat, $sesionsChannels);
            
            // add zero models
            $table_info = add_values_to_models($pages_info, $pages_info['totalUsersAllpages']);

            $dealer_info[$single_dealer['id']] = array(
                'totalUsers' => $pages_info['totalUsersAllpages'],
                'uniqUsers' => $pages_info['newUsers'],
                'totalModelPageUsers' => $table_info['totalUsersModelsPages'],
                'totalClickBroshure' => $table_info['totalClickBroshure'],
                'totalDownloadPrice' => $table_info['totalDownloadPrice'],
                'totalClick3d' => '',
                'totalClickTestDrive' => '',
                'totalSendTestDrive' => '',
                'totalClickOrderCarModelPage' => '',
                'totalClickMoreInfoOnlineCatalog' => '',
            );
        }
        
        return $dealer_info;
    }

    function get_table_info_by_model($first_date, $last_date, $dealers_id, $dev_cat = null, $session_channel = null){
        
        $array_sum = [];

        global $wpdb;

        // get info from db
        $sql = $wpdb->prepare("SELECT * FROM google_analytics_{$dealers_id} WHERE day between '$first_date' AND '$last_date'", ARRAY_A);
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (count($results)> 0){
        //unserialize data
        foreach($results as $single_date){
            $results_array[$single_date['day']] = array(
                'general_data' => json_decode($single_date['general_info'], true),
                'page_data' => json_decode($single_date['page_info'], true),
                'filter_general' => json_decode($single_date['filter_general'], true),
                'convertion_info' => json_decode($single_date['convertion_info'], true),
            );
            $dates[] = $single_date['day'];
        }
        $general_array[] = $results_array;
        $new_users = $total_users_all_pages = $total_users_models = $total_users_models_single = 0;
        foreach($dates as $single_date){
            foreach($general_array as $single_result){


                        if($dev_cat == null && $session_channel == null){ // check for filter
                            $array_sum['newUsers'] = isset($single_result[$single_date]['general_data']['newUsers']) ? $new_users += $single_result[$single_date]['general_data']['newUsers'] : $new_users;
                            $array_sum['totalUsersAllpages'] = isset($single_result[$single_date]['general_data']['totalUsersAllpages']) ? $total_users_all_pages += $single_result[$single_date]['general_data']['totalUsersAllpages'] : $total_users_all_pages;
                            
                            // Total model page users per day
                            if(isset($single_result[$single_date]['page_data'])){
                                foreach($single_result[$single_date]['page_data'] as $model_url => $model_page){
                                    if(!isset($array_sum['totalUsersModelsPages'][$model_url]['totalUsers'])) $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] = 0;
                                    $array_sum['totalUsersModels'] = isset($model_page['totalUsers']) ? $total_users_models += $model_page['totalUsers'] : $total_users_models;
                                    $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] += isset($model_page['totalUsers']) ? $model_page['totalUsers'] : 0;
                                    $array_sum['totalUsersModelsPages'][$model_url]['bounceRate'][] = $model_page['bounceRate'];
                                    $array_sum['totalUsersModelsPages'][$model_url]['userEngagementDuration'][] = $model_page['userEngagementDuration'];
                                }
                            }

                            // get conversions
                            if(isset($single_result[$single_date]['convertion_info']['device_cat'])){
                                foreach($single_result[$single_date]['convertion_info']['device_cat'] as $single_cat){
                                    foreach($single_cat as $sesion_channel){
                                        foreach($sesion_channel as $conv_label => $single_conversion){
                                            $check_for_value = '';
                                            $check_for_value = check_convertion_for_value($conv_label, $single_conversion);
                                            if(!empty($check_for_value[1])){
                                                if(!isset($array_sum['conversions'][$check_for_value[1]][$check_for_value[2]])) $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] = 0;
                                                $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] += $check_for_value[0];
                                            }
                                        }
                                    }
                                }
                            }
                            
                        }
                        
                        if($dev_cat != null || $session_channel != null){ // filter is on
                                $single_data_new_users = isset($single_result[$single_date]['filter_general']['newUsers']['device_cat']) ? $single_result[$single_date]['filter_general']['newUsers']['device_cat'] : null;
                                $single_data_total_users = isset($single_result[$single_date]['filter_general']['totalUsersAllpages']['device_cat']) ? $single_result[$single_date]['filter_general']['totalUsersAllpages']['device_cat'] : null;

                                if($dev_cat == null && $session_channel != null){
                                    foreach($session_channel as $single_chanel){
                                        $array_sum['newUsers'] = isset($single_data_new_users) ? $new_users += array_sum(array_column($single_data_new_users, $single_chanel)) : $new_users;
                                        $array_sum['totalUsersAllpages'] = isset($single_data_total_users) ? $total_users_all_pages += array_sum(array_column($single_data_total_users, $single_chanel)) : $total_users_all_pages;
                                        
                                        
                                        // Total model page users per day
                                        if(isset($single_result[$single_date]['page_data'])){
                                            foreach($single_result[$single_date]['page_data'] as $model_url => $model_page){
                                                if(!isset($array_sum['totalUsersModelsPages'][$model_url]['totalUsers'])) $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] = 0;
                                                $totalInfo = isset($model_page['device_cat']) ? array_column($model_page['device_cat'], $single_chanel) : '';
                                                $array_sum['totalUsersModels'] = !empty($totalInfo) ? $total_users_models += $totalInfo[0]['totalUsers'] : $total_users_models;
                                                $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] += !empty($totalInfo) ? $totalInfo[0]['totalUsers'] : 0;
                                                $array_sum['totalUsersModelsPages'][$model_url]['bounceRate'][] = !empty($totalInfo) ? $totalInfo[0]['bounceRate'] : '';
                                                $array_sum['totalUsersModelsPages'][$model_url]['userEngagementDuration'][] = !empty($totalInfo) ? $totalInfo[0]['userEngagementDuration'] : '';
                                            }
                                        }

                                        // get conversions
                                        if(isset($single_result[$single_date]['convertion_info']['device_cat'])){
                                            foreach($single_result[$single_date]['convertion_info']['device_cat'] as $single_cat){
                                                foreach($single_cat as $channel_label => $sesion_channel){
                                                    
                                                    if($channel_label != $single_chanel) continue; // check if current channel is same as filter
                                                    
                                                    foreach($sesion_channel as $conv_label => $single_conversion){
                                                        $check_for_value = '';
                                                        $check_for_value = check_convertion_for_value($conv_label, $single_conversion);
                                                        if(!empty($check_for_value[1])){
                                                            if(!isset($array_sum['conversions'][$check_for_value[1]][$check_for_value[2]])) $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] = 0;
                                                            $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] += $check_for_value[0];
                                                        }

                                                    }

                                                }
                                            }
                                        }

                                    }
                                    
                                }elseif($session_channel == null && $dev_cat != null){

                                    foreach($dev_cat as $single_cat){
                                        $array_sum['newUsers'] = isset($single_data_new_users[$single_cat]) ? $new_users += array_sum($single_data_new_users[$single_cat]) : $new_users;
                                        $array_sum['totalUsersAllpages'] = isset($single_data_total_users[$single_cat]) ? $total_users_all_pages += array_sum($single_data_total_users[$single_cat]) : $total_users_all_pages;

                                        // Total model page users per day
                                        if(isset($single_result[$single_date]['page_data'])){
                                            foreach($single_result[$single_date]['page_data'] as $model_url => $model_page){
                                                $totalUsers = isset($model_page['device_cat'][$single_cat]) ? $model_page['device_cat'][$single_cat] : '';
                                                if(!empty($totalUsers)){
                                                    foreach($totalUsers as $single){
                                                        if(!isset($array_sum['totalUsersModelsPages'][$model_url]['totalUsers'])) $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] = 0; 
                                                        $array_sum['totalUsersModels'] = isset($single['totalUsers']) ? $total_users_models += $single['totalUsers'] : $total_users_models;
                                                        $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] += !empty($single['totalUsers']) ? $single['totalUsers'] : 0;
                                                        $array_sum['totalUsersModelsPages'][$model_url]['bounceRate'][] = !empty($single['bounceRate']) ? $single['bounceRate'] : '';
                                                        $array_sum['totalUsersModelsPages'][$model_url]['userEngagementDuration'][] = !empty($single['userEngagementDuration']) ? $single['userEngagementDuration'] : '';
                                                        
                                                    }
                                                }
                                            }
                                        }

                                        // get conversions
                                        if(isset($single_result[$single_date]['convertion_info']['device_cat'])){
                                            foreach($single_result[$single_date]['convertion_info']['device_cat'] as $cat_label => $single_category){

                                                if($cat_label != $single_cat) continue; // check if current cat is in filter

                                                foreach($single_category as $sesion_channel){                                                    
                                                    foreach($sesion_channel as $conv_label => $single_conversion){
                                                        $check_for_value = '';
                                                        $check_for_value = check_convertion_for_value($conv_label, $single_conversion);
                                                        if(!empty($check_for_value[1])){
                                                            if(!isset($array_sum['conversions'][$check_for_value[1]][$check_for_value[2]])) $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] = 0;
                                                            $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] += $check_for_value[0];
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    
                                }else{
                                    foreach($dev_cat as $single_cat){
                                        foreach($session_channel as $single_channel){
                                            $array_sum['newUsers'] = isset($single_data_new_users[$single_cat][$single_channel]) ? $new_users += $single_data_new_users[$single_cat][$single_channel] : $new_users;
                                            $array_sum['totalUsersAllpages'] = isset($single_data_new_users[$single_cat][$single_channel]) ? $total_users_all_pages += $single_data_total_users[$single_cat][$single_channel] : $total_users_all_pages;

                                            // Total model page users per day
                                            if(isset($single_result[$single_date]['page_data'])){
                                                foreach($single_result[$single_date]['page_data'] as $model_url => $model_page){
                                                    if(!isset($array_sum['totalUsersModelsPages'][$model_url]['totalUsers'])) $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] = 0;
                                                    $array_sum['totalUsersModels'] = isset($model_page['device_cat'][$single_cat][$single_channel]['totalUsers']) ? $total_users_models += $model_page['device_cat'][$single_cat][$single_channel]['totalUsers'] : $total_users_models;
                                                    $array_sum['totalUsersModelsPages'][$model_url]['totalUsers'] += isset($model_page['device_cat'][$single_cat][$single_channel]['totalUsers']) ? $model_page['device_cat'][$single_cat][$single_channel]['totalUsers'] : 0;
                                                    $array_sum['totalUsersModelsPages'][$model_url]['bounceRate'][] = isset($model_page['device_cat'][$single_cat][$single_channel]['bounceRate']) ? $model_page['device_cat'][$single_cat][$single_channel]['bounceRate'] : '';
                                                    $array_sum['totalUsersModelsPages'][$model_url]['userEngagementDuration'][] = isset($model_page['device_cat'][$single_cat][$single_channel]['userEngagementDuration']) ? $model_page['device_cat'][$single_cat][$single_channel]['userEngagementDuration'] : '';
                                                    
                                                }
                                            }

                                            // get conversions
                                            if(isset($single_result[$single_date]['convertion_info']['device_cat'])){
                                                foreach($single_result[$single_date]['convertion_info']['device_cat'] as $cat_label => $single_category){

                                                    if($cat_label != $single_cat) continue; // check if current cat is in filter
                                                    
                                                    foreach($single_category as $channel_label =>  $sesion_channel){
                                                        
                                                        if($channel_label != $single_channel) continue; // check if current channel is same as filter

                                                            foreach($sesion_channel as $conv_label => $single_conversion){
                                                                $check_for_value = '';
                                                                $check_for_value = check_convertion_for_value($conv_label, $single_conversion);
                                                                if(!empty($check_for_value[1])){
                                                                    if(!isset($array_sum['conversions'][$check_for_value[1]][$check_for_value[2]])) $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] = 0;
                                                                    $array_sum['conversions'][$check_for_value[1]][$check_for_value[2]] += $check_for_value[0];
                                                                }
                                                            }
                                                    }
                                                }
                                            }


                                        }
                                    }
                                }

                        }
            }
        }

            return $array_sum;
        }else{
            return null;
        }
    }


    // get metrics
    register_rest_route('analytics/v1', 'metrics', array(
        'methods' => WP_REST_Server::READABLE,
        'callback' => function (WP_REST_Request $request) {
            
            $data = array(
                array(
                    'id' => "totalUsers",
                    'name' => "Усі користувачі",
                    'group' => array(0, 1, 2, 3)
                ),
                array(
                    'id' => "uniqUsers",
                    'name' => "Унікальні користувачі",
                    'group' => array(0, 1, 2, 3)
                ),
                // click model page (кількість користувачів які відвідали детільні сторінки моделей)
                array(
                    'id' => "totalModelPageUsers",
                    'name' => "Сторінка моделей",
                    'group' => array(1)
                ),
                // click broshure
                array(
                    'id' => "totalClickBroshure",
                    'name' => "Завантажили брошуру",
                    'group' => array(1)
                ),
                // click download price
                array(
                    'id' => "totalDownloadPrice",
                    'name' => "Завантажити прайс-лист",
                    'group' => array(1)
                ),
                // click 3d view
                array(
                    'id' => "totalClick3d",
                    'name' => "3D огляд",
                    'group' => array(1)
                ),
                // click test drive
                array(
                    'id' => "totalClickTestDrive",
                    'name' => "Тест драйв",
                    'group' => array(0, 1)
                ),
                // click send test drive
                array(
                    'id' => "totalSendTestDrive",
                    'name' => "Надіслати тест драйв",
                    'group' => array(0, 1)
                ),
                // click order car
                array(
                    'id' => "totalClickOrderCarModelPage",
                    'name' => "Замовити авто",
                    'group' => array(1)
                ),
                // click more info (сторінка онлайн склад)
                array(
                    'id' => "totalClickMoreInfoOnlineCatalog",
                    'name' => "Додаткова інформація (Онлайн каталог)",
                    'group' => array(2)
                ),
                // click order car (сторінка онлайн склад)
                array(
                    'id' => "totalClickOrderCarOnlineCatalog",
                    'name' => "Замовити авто (онлайн каталог)",
                    'group' => array(2)
                ),
                // click send car (сторінка онлайн склад)
                array(
                    'id' => "totalSendOrderOnlineCatalog",
                    'name' => "Відправити форму (онлайн каталог)",
                    'group' => array(2)
                ),
                // click create car
                array(
                    'id' => "totalClickCreateCar",
                    'name' => "Створити авто",
                    'group' => array(3)
                ),
                // click button (сторінка конфігуратор)
                array(
                    'id' => "totalCarTypeConfugurator",
                    'name' => "Обрати тип автомобіля",
                    'group' => array(3)
                ),
                // click button configuration
                array(
                    'id' => "totalCarSetConfugurator",
                    'name' => "Обрати комплектацію",
                    'group' => array(3)
                ),
                // click button choose exterior
                array(
                    'id' => "totalCarSetConfugurator",
                    'name' => "Вибрати екстер'єр",
                    'group' => array(3)
                ),
                // click button choose interior
                array(
                    'id' => "totalExteriorColorConfugurator",
                    'name' => "Обрати колір кузова",
                    'group' => array(3)
                ),
                // click button choose interior
                array(
                    'id' => "totalInteriorColorConfugurator",
                    'name' => "Колір інтер'єру",
                    'group' => array(3)
                ),
                // click button additional options
                array(
                    'id' => "totalAdditionalConfugurator",
                    'name' => "Додаткові налаштування",
                    'group' => array(3)
                ),
                // click button My HAVAL
                array(
                    'id' => "totalClickMyHavalConfugurator",
                    'name' => "My HAVAL",
                    'group' => array(3)
                ),
                // click button Order a car
                array(
                    'id' => "totalClickOrderCarConfugurator",
                    'name' => "Замовити авто (конфігуратор)",
                    'group' => array(3)
                ),
                // click button Order a car
                array(
                    'id' => "totalSendOrderCar",
                    'name' => "відправити форму (конфігуратор)",
                    'group' => array(3)
                ),
            );

            return $data;
            
        },
        'permission_callback' => function ($request) {
            return is_user_logged_in();
        }
    ));

    function get_all_deallers(){
        global $wpdb;
        $sql = "SELECT data FROM analytics_general WHERE name = 'dealers'";
        $dealers = $wpdb->get_results($sql, ARRAY_A);
        $data = json_decode($dealers[0]['data'], true);
        return $data;
    }

    function get_models(){
        global $wpdb;
        $sql = "SELECT data FROM analytics_general WHERE name = 'models'";
        $devCat = $wpdb->get_results($sql, ARRAY_A);
        $data = json_decode($devCat[0]['data'], true);
        return $data;
    }

    function get_average($array){
        $array = array_filter($array);
        $averageTimeOnSite = 0;
        if(count($array)) {
            $averageTimeOnSite = array_sum($array)/count($array);
        }
        return $averageTimeOnSite;
    }

    function check_convertion_for_value($conversion, $value){
        $type = '';

        $conversion = str_replace(' ', '_', $conversion);
        // broshure
        switch ($conversion) {
            case 'GA4_H6_download_broshure';
            case 'GA4_H6_download_price';
                $model = 'haval-h6-l2';
                break;
            case 'GA4_Dargo_download_broshure';
            case 'GA4_Dargo_download_price';
                $model = 'haval-dargo';
                break;
            case 'GA4_H6_gt_download_broshure';
            case 'GA4_H6_gt_download_price';
                $model = 'haval-h6-gt';
                break;
            case 'GA4_H6_hev_download_broshure';
            case 'GA4_H6_hev_download_price';
                $model = 'haval-h6-hev';
                break;
            case 'GA4_Jolion_download_broshure';
            case 'GA4_Jolion_download_price';
                $model = 'haval-jolion';
                break;
            default:
                $model = '';
        }

        if (str_contains($conversion, 'download_broshure')) $type = 'totalClickBroshure';
        if (str_contains($conversion, 'download_price')) $type = 'totalDownloadPrice';
        
        return array($value, $model, $type);
    }

    function add_values_to_models($info, $totalUsesOnSite){
        $new_array = [];
               
        $models = get_models(); // get all models from db
        foreach($models as $single_model){
            $value = $total_users = 0;
            $CVR = $bounceRate = $time_on_site = '';
            if(isset($info['totalUsersModelsPages']) && is_array($info['totalUsersModelsPages'])){
                foreach($info['totalUsersModelsPages'] as $key => $single){
                    
                    if($key == $single_model['id']){
                        $CVR = !empty($single['totalUsers']) && $totalUsesOnSite != 0 ? number_format(($single['totalUsers'] * 100) / $totalUsesOnSite, 2) : '';
                        $bounceRate = isset($single['bounceRate']) ? number_format(get_average($single['bounceRate']), 3) * 100 : '';
                        $time_on_site = isset($single['userEngagementDuration']) ? number_format(get_average($single['userEngagementDuration']), 0)  : '';
                        $total_users = isset($single['totalUsers']) ? $single['totalUsers'] : 0;
                    }
                }
            }
            // convert time
            $new_array['totalModelPage']['CVR'][$single_model['id']] = !empty($CVR) ? $CVR.'%' : '';
            $new_array['totalModelPage']['bounce'][$single_model['id']] = !empty($bounceRate) ? $bounceRate.'%' : '';
            $new_array['totalModelPage']['time_on_site'][$single_model['id']] = convert_time_in_minutes($time_on_site);
            $new_array['totalModelPage']['total_users'][$single_model['id']] = $total_users;

            // broshures
            $new_array['download_broshure']['total'][$single_model['id']] = isset($info['conversions'][$single_model['id']]['totalClickBroshure']) ? $info['conversions'][$single_model['id']]['totalClickBroshure'] : 0;
            $new_array['download_broshure']['CVR'][$single_model['id']] = isset($info['conversions'][$single_model['id']]['totalClickBroshure']) && $total_users != 0 ? number_format(($info['conversions'][$single_model['id']]['totalClickBroshure'] * 100) / $total_users, 2).'%' : '';

            // price download
            $new_array['download_price']['total'][$single_model['id']] = isset($info['conversions'][$single_model['id']]['totalDownloadPrice']) ? $info['conversions'][$single_model['id']]['totalDownloadPrice'] : 0;
            $new_array['download_price']['CVR'][$single_model['id']] = isset($info['conversions'][$single_model['id']]['totalDownloadPrice']) && $total_users != 0 ? number_format(($info['conversions'][$single_model['id']]['totalDownloadPrice'] * 100) / $new_array['totalModelPage']['total_users'][$single_model['id']], 2).'%' : '';
        }

        // total Users models pages
        $responce['totalUsersModelsPages'][] = array(
            'label' => 'Сторінка моделей',
            'description' => __('К-сть користувачів, які відвідали детальні сторінки моделей', 'analytics'),
            'data' => $new_array['totalModelPage']['total_users'],
        );
        $responce['totalUsersModelsPages'][] = array(
            'label' => 'CVR %',
            'description' => __('Відсоток користувачів, які відвідали сторінку моделі, по модельно до загальної кількості відвідувачів', 'analytics'),
            'data' => $new_array['totalModelPage']['CVR'],
        );
        $responce['totalUsersModelsPages'][] = array(
            'label' => 'Bounces',
            'description' => __('Відсоток користувачів, які припинили сеанс та залишили сайт на даній сторінці, по модельно, до загальної кількості  відвідувачів', 'analytics'),
            'data' => $new_array['totalModelPage']['bounce'],
        );
        $responce['totalUsersModelsPages'][] = array(
            'label' => 'Average time on site',
            'description' => __('Середня тривалість відвідування даної сторінки моделі', 'analytics'),
            'data' => $new_array['totalModelPage']['time_on_site'],
        );

        // broshures
        $responce['totalClickBroshure'][] = array(
            'label' => 'Кнопка "Завантажити брошуру"',
            'description' => __('Клік "Завантажити брошуру"', 'analytics'),
            'data' => $new_array['download_broshure']['total'],
        );
        $responce['totalClickBroshure'][] = array(
            'label' => 'CVR %',
            'description' => __('Відсоток користувачів, які здійснили конверсію, по модельно до загальної кількості відвідувачів сторінки моделі', 'analytics'),
            'data' => $new_array['download_broshure']['CVR'],
        );

        // download price
        $responce['totalDownloadPrice'][] = array(
            'label' => 'Кнопка "Завантажити прайс-лист"',
            'description' => __('Клік "Завантажити прайс-лист"', 'analytics'),
            'data' => $new_array['download_price']['total'],
        );
        $responce['totalDownloadPrice'][] = array(
            'label' => 'CVR %',
            'description' => __('Відсоток користувачів, які здійснили конверсію, по модельно до загальної кількості відвідувачів сторінки моделі', 'analytics'),
            'data' => $new_array['download_price']['CVR'],
        );

        return $responce;
    }


    function get_total_exits_grahic(WP_REST_Request $request){
       
        global $wpdb;
        $results_array = $total_users_array = $total_exits = [];
        $first_date = $request->get_param('dateFrom');
        $last_date = (!empty($request->get_param('dateTo')) && $request->get_param('dateTo') != '1970-01-1') ? $request->get_param('dateTo') : $first_date;
        $dealers_id = !empty($request->get_param('dealers')) ? $request->get_param('dealers') : null;
        
        
        if($dealers_id == null){
            $all_dealers_info = get_all_deallers();
            $general_array = [];
            foreach($all_dealers_info as $single_dealer){
                $single_dealer_id = $single_dealer['id'];
                $sql = $wpdb->prepare("SELECT total_by_page, exit_pages, day FROM google_analytics_{$single_dealer['id']} WHERE day between '$first_date' AND '$last_date'", ARRAY_A);
                $results = $wpdb->get_results($sql, ARRAY_A);

                //unserialize data
                foreach($results as $single_date){
                    $merged_array = [];
                    // single general info
                    $total_pages = json_decode($single_date['total_by_page'], true);
                    $exits = json_decode($single_date['exit_pages'], true);
                    if($total_pages != null && $exits != null){
                        $merged_array = array_merge_recursive($total_pages, $exits);
                        foreach($merged_array as $slug => $single_value){
                            $label = replace_slug($slug);
                            if(!is_array($single_value) || count($single_value) < 2 || !$label) continue;
                            
                            $results_array[$label]['total'] += isset($single_value[0]) ? intval($single_value[0]) : 0;
                            $results_array[$label]['exits'] += isset($single_value[1]) ? intval($single_value[1]) : 0;
                        }
                    } 
                }
            }
        }else{
            $sql = $wpdb->prepare("SELECT total_by_page, exit_pages, day FROM google_analytics_{$dealers_id} WHERE day between '$first_date' AND '$last_date'", ARRAY_A);
            $results = $wpdb->get_results($sql, ARRAY_A);

            //unserialize data
            foreach($results as $single_date){
                $merged_array = [];
                // single general info
                $total_pages = json_decode($single_date['total_by_page'], true);
                $exits = json_decode($single_date['exit_pages'], true);
                if($total_pages != null && $exits != null){
                    $merged_array = array_merge_recursive($total_pages, $exits);
                    foreach($merged_array as $slug => $single_value){
                        $label = replace_slug($slug);
                        if(!is_array($single_value) || count($single_value) < 2 || !$label) continue;
                        
                        $results_array[$label]['total'] += isset($single_value[0]) ? intval($single_value[0]) : 0;
                        $results_array[$label]['exits'] += isset($single_value[1]) ? intval($single_value[1]) : 0;
                    }
                }
                
            }
        }

        foreach($results_array as $key => $single){
            // print_r($single);
            $total_users_array[] = array(
                'label' => $key,
                'data' => $single['total'],
            );
            $total_exits[] = array(
                'label' => $key,
                'data' => $single['exits'],
            );
        }

        return array($total_users_array, $total_exits);
    }

    function replace_slug($slug){

        switch ($slug) {
            case '/':
                $value = "Головна";
                break;
            case '/katalog/':
                $value = "Каталог";
                break;
            case '/car/haval-h6-gt/':
                $value = "H6 GT";
                break;
            case '/car/haval-h6-hev/':
                $value = "H6 Hev";
                break;
            case '/car/haval-h6-l2/':
            case '/car/haval-h6-tretogo-pokolinnya/':
                $value = "H6";
                break;
            case '/car/haval-jolion/':
                $value = "Jolion";
                break;
            case '/car/haval-dargo/':
                $value = "Dargo";
                break;
            case '/car/great-wall-wingle-7/':
                $value = "Wingle 7";
                break;
            case '/3d-model-mobile/':
                $value = "3d огляд";
                break;
            case '/konfigurator/':
                $value = "Конфігуратор";
                break;
            case '/about/':
                $value = "Про нас";
                break;
            case '/servises/':
                $value = "Сервіс";
                break;
            case '/novosti/':
                $value = "Новини";
                break;
            case '/contact/':
                $value = "Контакти";
                break;
            default:
                $value = false;
        }

        return $value;
    }

    // get users
    function get_total_users(WP_REST_Request $request){
       
        global $wpdb;
        $results_array = $responce_array = [];
        $first_date = $request->get_param('dateFrom');
        $last_date = (!empty($request->get_param('dateTo')) && $request->get_param('dateTo') != '1970-01-1') ? $request->get_param('dateTo') : $first_date;
        $dealers_id = !empty($request->get_param('dealers')) ? $request->get_param('dealers') : null;
        $channels = !empty($request->get_param('channels')) ? $request->get_param('channels') : null;
        $devices = !empty($request->get_param('devices')) ? $request->get_param('devices') : null;
        $currentPage = !empty($request->get_param('currentPage')) ? $request->get_param('currentPage') : 0;
        $itemsPerPage = !empty($request->get_param('itemsPerPage')) ? $request->get_param('itemsPerPage') : 10;

        if($dealers_id == null){
            $all_dealers_info = get_all_deallers();
            $general_array = [];
            foreach($all_dealers_info as $single_dealer){
                $dealers_id = $single_dealer['id'];
                $users_array[$dealers_id] = get_users_info_from_db($first_date, $last_date, $dealers_id, $channels, $devices);
            }
            foreach($users_array as $single_dealer){
                $responce_array = array_merge($responce_array, $single_dealer['user_array']);
                $users_array['items_count'] += $single_dealer['items_count'];
            }
            
        }else{
            $users_array = get_users_info_from_db($first_date, $last_date, $dealers_id, $channels, $devices);
            $responce_array = $users_array['user_array'];
        }

        // pagination
        $chunk_users = array_chunk($responce_array, $itemsPerPage);

        $responce = array(
            'data' => $chunk_users[$currentPage],
            'items' => $users_array['items_count'],
        );

        return $responce;
    }

    function get_users_info_from_db($first_date, $last_date, $dealers_id, $channels, $devices){

        global $wpdb;
        $users_array = [];
        $items_count = 0;
        if($channels == null && $devices == null){
            $sql = $wpdb->prepare("SELECT * FROM analytics_users_{$dealers_id} WHERE date between '$first_date' AND '$last_date'", ARRAY_A);
        }else if($channels != null && $devices == null){
            $source = implode('\',\'', $channels);
            $sql = $wpdb->prepare("SELECT * FROM analytics_users_{$dealers_id} WHERE date between '$first_date' AND '$last_date' AND source IN ('{$source}')", ARRAY_A);
        }else if($channels == null && $devices != null){
            $dev_cats = implode('\',\'', $devices);
            $sql = $wpdb->prepare("SELECT * FROM analytics_users_{$dealers_id} WHERE date between '$first_date' AND '$last_date' AND device_cat IN ('{$dev_cats}')", ARRAY_A);
        }else if($channels != null && $devices != null){
            $source = implode('\',\'', $channels);
            $dev_cats = implode('\',\'', $devices);
            $sql = $wpdb->prepare("SELECT * FROM analytics_users_{$dealers_id} WHERE date between '$first_date' AND '$last_date' AND device_cat IN ('{$dev_cats}') AND source IN ('{$source}')", ARRAY_A);
        }
        $results = $wpdb->get_results($sql, ARRAY_A);
        //unserialize data
        if(!empty($results)){
            $items_count += count($results);
            foreach($results as $single_user){
                if(!empty($single_user['page_time'])){
                    $page_path = explode('/', json_decode($single_user['page_time'], true));
                    $new_path = $time = [];
                    foreach($page_path as $single_path){
                        $info = explode(' = ', $single_path);
                        $new_path[] = array(
                            'label' => $info[0],
                            'data' => $info[1],
                        );
                        $time[] = $info[1];
                    }
                } 
                $users_array[] = array(
                'id' => $single_user['user_id'],
                'date' => $single_user['date'],
                'device' => device_cat_rename($single_user['device_cat']),
                'source' => $single_user['source'],
                'referrer' => $single_user['referal'],
                'time' => array_sum($time),
                'page_path' => array_map('trim', explode('/', json_decode($single_user['page_journey'], true))),
                'page_time' => $new_path,
                );
            }

            $responce = array(
                'user_array' => $users_array,
                'items_count' => $items_count,
            );

            return $responce;
        }else{

            $responce = array(
                'user_array' => [],
                'items_count' => 0,
            );

            return $responce;
        }

    }

    function device_cat_rename($data){

        switch($data){
            case 'mobile':
                $value = __('Смартфон', 'analytics');
                break;
            case 'desktop':
                $value = __('Десктоп', 'analytics');
                break;
            case 'tablet':
                $value = __('Планшет', 'analytics');
                break;  
        }

        return $value;
    }

    function convert_time_in_minutes($time){
        $new_time = '';
        if(empty($time)) return $new_time;
        
        $H = floor(intval($time) / 3600);
        $i = (intval($time) / 60) % 60;
        $s = intval($time) % 60;
        if($H){
            $new_time = sprintf("%dг. %dхв. %dс.", $H, $i, $s);
        }else{
            $new_time = sprintf("%dхв. %dс.", $i, $s);
        }
        return $new_time;
    }

    // export users into csv file
    function export_users(WP_REST_Request $request){

        // get dealers data
        global $wpdb;
        $results_array = $responce_array = [];
        $first_date = $request->get_param('dateFrom');
        $last_date = (!empty($request->get_param('dateTo')) && $request->get_param('dateTo') != '1970-01-1') ? $request->get_param('dateTo') : $first_date;
        $dealers_id = !empty($request->get_param('dealers')) ? $request->get_param('dealers') : null;
        $channels = !empty($request->get_param('channels')) ? $request->get_param('channels') : null;
        $devices = !empty($request->get_param('devices')) ? $request->get_param('devices') : null;

        if($dealers_id == null){
            $all_dealers_info = get_all_deallers();
            $general_array = [];
            foreach($all_dealers_info as $single_dealer){
                $dealers_id = $single_dealer['id'];
                $users_array[$dealers_id] = get_users_info_from_db($first_date, $last_date, $dealers_id, $channels, $devices);
            }
            foreach($users_array as $single_dealer){
                $responce_array = array_merge($responce_array, $single_dealer['user_array']);
                $users_array['items_count'] += $single_dealer['items_count'];
            }
            
        }else{
            $users_array = get_users_info_from_db($first_date, $last_date, $dealers_id, $channels, $devices);
            $responce_array = $users_array['user_array'];
        }

        $file_name = 'users'.$first_date.'_'.$last_date.'.csv';
        // create csv file
        $file_link_dir = str_replace('/', DIRECTORY_SEPARATOR, wp_upload_dir()['basedir']) . DIRECTORY_SEPARATOR .'analitycs-users-export'.DIRECTORY_SEPARATOR. $file_name;

        // Create an array of elements
        if(!empty($responce_array)){
            $list[0] = array_keys($responce_array[0]);
            foreach($responce_array as $key => $single_user){
                $page_on_time = '';
                // on time page
                foreach($single_user['page_time'] as $single_path){
                    $page_on_time .= $single_path['label'].' = '.$single_path['data'];
                }
                // $single_user_device = mb_convert_encoding($single_user['device'], "UTF-8","windows-1251");
                $list[] = array($single_user['id'], $single_user['date'], $single_user['device'], $single_user['source'], $single_user['referrer'], convert_time_in_minutes($single_user['time']), implode(' -> ', $single_user['page_path']), $page_on_time);

            }
            
            // Open a file in write mode ('w')
            $fp = fopen($file_link_dir, 'w');
            $BOM = "\xEF\xBB\xBF";
            fwrite($fp, $BOM);
            // Loop through file pointer and a line
            foreach ($list as $fields) {
                fputcsv($fp, $fields, ";");
            }
            fclose($fp);

                        
            // If file exist on server
            if( @file_exists($file_link_dir) ){

                if (@$stream = fopen($file_link_dir, 'r')) {

                        ob_start();
                            echo stream_get_contents($stream, filesize($file_link_dir));
                        $result = ob_get_contents();
                        ob_get_clean();

                    fclose($stream);
                    return $result;
                }

            }else{

                $result = array(
                    'success' => false,
                    'message' => __( 'There was ann error with file', 'analytics' ),
                );

                return $result;
            }
        }else{

                $result = array(
                    'success' => false,
                    'message' => __( 'No Users found', 'analytics' ),
                );

                return $result;
        }

    }


});


