<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Availibility_Search extends Public_Controller
{
  public $perpage = 15;

  public function __construct()
  {
    parent::__construct();

    // Load the required classes
    $this->load->driver('Streams');
    $this->load->library('files/files');
    $this->lang->load('firesale/firesale');
    $this->load->model('availibility_search_m');
    $this->load->model('firesale/categories_m');
    $this->load->model('firesale/products_m');
    $this->load->model('firesale/routes_m');
    $this->load->helper('firesale/general');
    $this->template->append_js('module::search.js');
  }

  /**
  * All items
  */
  public function search()
  {
    // get input data
    $start_date = $this->input->get('start_date');
    $length_of_stay = $this->input->get('length_of_stay');
    $party_size = $this->input->get('party_size');
    $location = $this->input->get('location');
    $late_booking = $this->input->get('late_booking');
    $results = [];

    // $days_extra = explode(" ",$length_of_stay);
    $this->input->get('per_page') > 0 ? $start = (int)( ( $this->input->get('per_page') -1 ) * $this->perpage) : $start = 0;

    // get entries
    $results['entries'] = $this->availibility_search_m->search($start_date, $length_of_stay, $party_size, $location, $late_booking, $this->perpage, $start);

    $this->template
    ->set('products', $products)
    ->build('index');
  }

  public function getsingle($slug)
  {
    // set params
    $params = array(
      'stream'        => 'whatson',
      'namespace'     => 'streams',
      'limit'			=> '1',
      'where'			=> "`slug` = '$slug'",
      'paginate'		=> 'yes',
      'pag_base'		=> site_url('whatson'),
      'pag_segment'   => 2
      );

    // get entries
    $entries = $this->streams->entries->get_entries($params);

    $this->template
    ->title($this->module_details['name'], 'the rest of the page title')
    ->set('entries', $entries['entries'])
    ->build('event');
  }

}