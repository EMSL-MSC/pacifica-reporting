<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * This serves as a landing/redirect page for reporting functionality now
 * Could probably just as easily do this with a routing command
*/


class Reporting extends CI_Controller
{


    function __construct()
    {
        parent::__construct();
        $this->load->helper(array('url'));

    }//end __construct()


    public function index()
    {
        redirect('group/view');

    }//end index()


    public function group_view($object_type, $time_range = FALSE, $start_date = FALSE, $end_date = FALSE, $time_basis = FALSE)
    {
        $url = rtrim("group/view/{$object_type}/{$time_range}/{$start_date}/{$end_date}/{$time_basis}", "/");
        redirect($url, 'location', 301);

    }//end group_view()


    public function view($object_type, $time_range = FALSE, $start_date = FALSE, $end_date = FALSE, $time_basis = FALSE)
    {
        $url = rtrim("item/view/{$object_type}/{$time_range}/{$start_date}/{$end_date}/{$time_basis}", "/");
        redirect($url, 'location', 301);

    }//end view()


}//end class
