<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once('Baseline_controller.php');

class Reporting extends Baseline_controller {
  public $last_update_time;
  public $accepted_object_types;
  
  function __construct() {
    parent::__construct();
    $this->load->model('Reporting_model','rep');
    $this->load->library('myemsl-eus-library/EUS','','eus');
    $this->load->helper(array('network','file_info','inflector','time','item'));
    $this->last_update_time = get_last_update(APPPATH);
    $this->accepted_object_types = array('instrument','user','proposal');
  }



  public function index(){
    redirect('reporting/view');
  }
  
  public function view($object_type, $time_range = '1-month', $start_date = false, $end_date = false){
    $time_range = str_replace(array('-','_','+'),' ',$time_range);
    if(!strtotime($time_range)){
      if($time_range == 'custom' && strtotime($start_date) && strtotime($end_date)){
        //custom date_range, just leave them. Canonicalize will fix them
      }else{
        //looks like the time range is borked, pick the default
        $time_range = '1 week';
        $times = time_range_to_date_pair($time_range);
        extract($times);
      }
    }else{
      $times = time_range_to_date_pair($time_range);
      extract($times);
    }
    $times = $this->rep->canonicalize_date_range($start_date, $end_date);
    extract($times);
    
    $object_type = singular($object_type);
    $accepted_object_types = array('instrument','proposal','user');
    if(!in_array($object_type,$accepted_object_types)){
      redirect('reporting/view/instrument');
    }
    $this->page_data['page_header'] = "MyEMSL Uploads per ".ucwords($object_type);
    $this->page_data['css_uris'] = array(
      "/resources/stylesheets/status_style.css",
      "/resources/scripts/select2/select2.css",
      base_url()."resources/stylesheets/reporting.css"   
    );
    $this->page_data['script_uris'] = array(
      "/resources/scripts/spinner/spin.min.js",
      "/resources/scripts/spinner/jquery.spin.js",
      "/resources/scripts/moment.min.js",
      base_url()."resources/scripts/highcharts/js/highcharts.js"
    );
    
    $this->page_data['my_objects'] = '';
    $my_object_list = $this->rep->get_selected_objects($this->user_id,$object_type);
    $object_list = array_map('strval', array_keys($my_object_list[$object_type]));
    if(!empty($default_object_id) && in_array($default_object_id,$object_list)){
      $object_list = array(strval($default_object_id));
    }
    // $transaction_info = array();
    $object_info = $this->eus->get_object_info($object_list,$object_type);
    // $transaction_retrieval_func = "summarize_uploads_by_{$object_type}";
    foreach($object_list as $object_id){
      // $transaction_info[$object_id] = $this->rep->$transaction_retrieval_func($object_id,'2015-10-01','2015-12-01');
      $this->page_data['placeholder_info'][$object_id] = array(
        'object_type' => $object_type,
        'object_id' => $object_id,
        'time_range' => $time_range,
        'times' => $times
      );
    }
    $this->page_data['default_time_range'] = $times;
    $this->page_data['content_view'] = "object_types/{$object_type}.html";
    $this->page_data['my_objects'] = $object_info;
    // $this->page_data['transaction_info'] = $transaction_info;
    
    $this->load->view('reporting_view.html',$this->page_data);
  }
  
    
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/* API functionality for Ajax calls from UI                  */
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

  // Call to retrieve fill-in HTML for reporting block entries
  public function get_reporting_info($object_type,$object_id,$time_range = '1-week', $start_date = false, $end_date = false){
    $latest_data = $this->rep->latest_available_data($object_type,$object_id);
    $latest_data_object = new DateTime($latest_data);
    $time_range = str_replace(array('-','_','+'),' ',$time_range);
    $this->page_data['results_message'] = "No data for this {$object_type} is available in the most recent {$time_range} period.";
    $valid_tr = strtotime($time_range);
    $valid_st = strtotime($start_date);
    $valid_et = strtotime($end_date);
    if(!$valid_tr){
      if($time_range == 'custom' && $valid_st && $valid_et){
        //custom date_range, just leave them. Canonicalize will fix them
      }else{
        //looks like the time range is borked, pick the default
        $time_range = '1 week';
        $times = time_range_to_date_pair($time_range,$latest_data_object);
      }
    }else{ //time_range is apparently valid
      if(($valid_st || $valid_et) && !($valid_st && $valid_et)){
        //looks like we want an offset time either start or finish 
        $times = time_range_to_date_pair($time_range,$latest_data_object,$start_date,$end_date);
      }else{
        $times = time_range_to_date_pair($time_range, $latest_data_object);
      }
    }
    extract($times);
    // $this->page_data['results_message'] .= $times['message'];
    
    $transaction_retrieval_func = "summarize_uploads_by_{$object_type}";
    $transaction_info = array();
    $transaction_info = $this->rep->$transaction_retrieval_func($object_id,$start_date,$end_date);
    $this->page_data['transaction_info'] = $transaction_info;
    $this->page_data["{$object_type}_id"] = $object_id;
    $this->page_data['object_type'] = $object_type;
    $this->page_data['times'] = $times;
    $this->load->view("object_types/{$object_type}_body_insert.html", $this->page_data);
  }




  public function get_uploads_for_instrument($instrument_id,$start_date = false,$end_date = false){
    $results = $this->rep->summarize_uploads_by_instrument($instrument_id,$start_date,$end_date);
    $results_size = sizeof($results);
    $pluralizer = $results_size != 1 ? "s" : "";
    $status_message = '{$results_size} transaction{$pluralizer} returned';
    send_json_array($results);
  }
  
  public function get_uploads_for_proposal($proposal_id,$start_date = false,$end_date = false){
    $results = $this->rep->summarize_uploads_by_proposal($proposal_id,$start_date,$end_date);
    send_json_array($results);
  }
  
  public function get_uploads_for_user($eus_person_id, $start_date = false, $end_date = false){
    $results = $this->rep->summarize_uploads_by_user($eus_person_id,$start_date,$end_date);
    send_json_array($results);
  }
  
  public function get_proposals($proposal_name_fragment, $active = 'active'){
    $results = $this->eus->get_proposals_by_name($proposal_name_fragment,$active);
    send_json_array($results);
  }
  
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/* Testing functionality                                     */
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
  public function test_get_proposals($proposal_name_fragment, $active = 'active'){
    $results = $this->eus->get_proposals_by_name($proposal_name_fragment,$active);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
  }
  
  public function test_get_uploads_for_user($eus_person_id,$start_date = false,$end_date = false){
    $results = $this->rep->summarize_uploads_by_user($eus_person_id,$start_date,$end_date);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
    
  }
  
  public function test_get_uploads_for_instrument($eus_instrument_id,$start_date = false,$end_date = false){
    $results = $this->rep->summarize_uploads_by_instrument($eus_instrument_id,$start_date,$end_date);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
    
  }

  
  public function test_get_selected_objects($eus_person_id){
    $results = $this->rep->get_selected_objects($eus_person_id);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
  }
  
  public function test_get_object_list($object_type,$filter = ""){
    $results = $this->eus->get_object_list($object_type,$filter);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
  }
  
  public function test_get_latest($object_type,$object_id){
    $results = $this->rep->latest_available_data($object_type,$object_id);
    echo "<pre>";
    var_dump($results);
    echo "</pre>";
    
  }
  

}

?>
