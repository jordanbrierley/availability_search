<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Availibility_Search_m extends MY_Model {

	public function __construct()
	{		
		parent::__construct();
s	}

	public function search($start_date = null, $days_extra = 3, $party_size = null, $location = null, $late_booking = null, $limit, $offset)
	{
		if ($start_date == null) {
			$start_date = date('Y-m-d',strtotime('+ 1 days'));
			$late_booking = 2;
		}

		// query builder
		$this->db->select('firesale_products.*, firesale_bookings.booking_date, firesale_bookings.booking_status, firesale_bookings.booking_arrival')->distinct();

		if ($start_date || $days_extra) {
			$booking_status_arrival = array('0', '2', '3');
			$this->db->join('firesale_bookings', "firesale_products.id = firesale_bookings.booking_product", "left");
			$this->db->where('firesale_bookings.booking_date BETWEEN "'.$start_date.'" and DATE_ADD("'.$start_date.'",INTERVAL 3 MONTH)');
			$this->db->where_in('firesale_bookings.booking_status', $booking_status_arrival);
			$this->db->where('firesale_bookings.booking_arrival = "1"');
			$this->db->order_by('firesale_bookings.booking_date', 'asc');
		}

		if ($party_size) {
			$this->db->join('firesale_attribute_assignments', "firesale_products.id = firesale_attribute_assignments.row_id", "left");
			$this->db->where('firesale_attribute_assignments.attribute_id = 1');
			$this->db->where('firesale_attribute_assignments.value BETWEEN "'.$party_size.'" and "8"');	
		}

		if ($location) {
			$this->db->join("firesale_products_firesale_categories", "firesale_products.id = firesale_products_firesale_categories.row_id", "left");
			$this->db->join("firesale_categories", "firesale_products_firesale_categories.firesale_categories_id = firesale_categories.id", "left");
			$this->db->where('firesale_categories.parent = '.$location);
		}

		$query = $this->db->get('firesale_products');
		$results = $query->result_array();

		$sort = array();
		foreach ($results as $key => $row)
		{
			$sort['id'][$key] = $row['id'];
			$sort['booking_date'][$key] = $row['booking_date'];

		}
		array_multisort($sort['id'], SORT_DESC, $sort['booking_date'], SORT_DESC, $results);


		$arrival_dates = array();
		$departure_dates = array();

		foreach ($results as $key => $value) {

			if ($value['booking_status'] == '0' || $value['booking_status'] == '2') {

				$arrival_dates[$value['id']] = $value;

				$this->db->select('firesale_product_variations.title AS var_title, firesale_product_variations.price AS var_price');
				$this->db->join('firesale_product_modifiers', "firesale_product_modifiers.id = firesale_product_variations.parent", "left");
				$this->db->where('firesale_product_modifiers.parent = '.$value['id']);
				$price_query = $this->db->get('firesale_product_variations');
				$price_results = $price_query->result_array();

				$arrival_dates[$value['id']]['price_variations'] = $price_results;
			}

			if ($value['booking_status'] == '0'  || $value['booking_status'] == '3' && !in_array($value['booking_date'], $arrival_dates[$value['id']])) 
			{
				$departure_dates[$key.'_'.$value['id']] = $value;
			}
		}

		$newsort = array();
		foreach ($departure_dates as $key => $row)
		{
			$newsort['id'][$key] = $row['id'];
			$newsort['booking_date'][$key] = $row['booking_date'];
		}
		array_multisort($newsort['id'], SORT_DESC, $newsort['booking_date'], SORT_DESC, $departure_dates);


		foreach ($results as $key => $value) {

			$arrival_dates[$value['id']]['booking_date'];

			$date = $arrival_dates[$value['id']]['booking_date'];
			$date1 = str_replace('-', '/', $date);
			$tomorrow = date('Y-m-d',strtotime($date1 . '+'.$days_extra.' days'));

			$this->db->select('firesale_bookings.booking_status');
			$this->db->where('firesale_bookings.booking_date BETWEEN "'.$date.'" and "'.$tomorrow.'"');
			$this->db->where('firesale_bookings.booking_product = '.$value['id']);
			$this->db->where('firesale_bookings.booking_status = "1"');
			$bookings_query = $this->db->get('firesale_bookings');
			$available_bookings = $bookings_query->result_array();

			if ($departure_dates[$key.'_'.$value['id']]['booking_date'] == $tomorrow && count($available_bookings) == 0)  {
				$arrival_dates[$value['id']]['departure_date'] = $departure_dates[$key.'_'.$value['id']]['booking_date'];
			}

			if ($arrival_dates[$value['id']]['departure_date'] == '') {

				foreach ($departure_dates as $key => $row)
				{
					if ( $row['booking_date'] <= $date ) {
						unset( $row );
					}

					if ($row['id'] == $value['id']) {
						$arrival_dates[$value['id']]['departure_date'] = $row['booking_date'];
					}
				}
			}

			$this->db->select('firesale_bookings.booking_price');
			$this->db->where('firesale_bookings.booking_date BETWEEN DATE_ADD("'.$date.'",INTERVAL 1 DAY) and "'.$arrival_dates[$value['id']]['departure_date'].'"');
			$this->db->where('firesale_bookings.booking_product = '.$value['id']);
			$total_price_query = $this->db->get('firesale_bookings');
			$total_price_results = $total_price_query->result_array();

			$tot_price = 0;
			foreach ($total_price_results as $t => $p) {
				$tot_price = $tot_price + $p['booking_price'];
			}

			$arrival_dates[$value['id']]['length_of_stay'] = round(abs(strtotime($arrival_dates[$value['id']]['departure_date'])-strtotime($arrival_dates[$value['id']]['booking_date']))/86400);

			foreach ($arrival_dates[$value['id']]['price_variations'] as $k => &$v) {
				$explodedkey = explode(" ", $v['var_title']);

				if ($explodedkey['0'] == $arrival_dates[$value['id']]['length_of_stay']) 
				{
					$arrival_dates[$value['id']]['booking_price'] = number_format($tot_price * $v['var_price'], 2);
				}
			}

			if ($arrival_dates[$value['id']]['length_of_stay'] < $days_extra && count($arrival_dates) > 0 ) 
			{
				unset($arrival_dates[$value['id']]['length_of_stay']);
			}

			if ($arrival_dates[$value['id']]['length_of_stay']  < 3 || $arrival_dates[$value['id']]['length_of_stay']  > 40 ){
				unset($arrival_dates[$value['id']]);
			}


			$late_date = date('Y-m-d',strtotime(date('Y-m-d') . '+ 14 days'));
			if ($start_date < $late_date) {
				$arrival_dates[$value['id']]['late_arrivals'] = $this->lateAvailibilites($value['id'], $start_date, $late_date, $arrival_dates[$value['id']]['departure_date'], $arrival_dates[$value['id']]['price_variations']);
			}

			if ( $late_booking == 2 && $arrival_dates[$value['id']]['late_arrivals'] == '') {
				unset($arrival_dates[$value['id']]);
			}

		}

		$final_sort = array();
		foreach ($arrival_dates as $key => $row)
		{
	//echo $row['id'].' + '.$row['booking_date'].'<br/>';
			$final_sort['length_of_stay'][$key] = $row['length_of_stay'];
			$final_sort['booking_date'][$key] = $row['booking_date'];
		}
		array_multisort($final_sort['booking_date'], SORT_ASC, $final_sort['length_of_stay'], SORT_ASC, $arrival_dates);

		$products = array();
		foreach ($arrival_dates AS $arrivalDateEntry) {
			if ($arrivalDateEntry['length_of_stay'] >= 7) {

				$earliest_arrival_date = explode('-', $arrivalDateEntry['booking_date']);
				$departure_date = explode('-', $arrivalDateEntry['departure_date']);	 		
				$product    			= $this->pyrocache->model('products_m', 'get_product', $arrivalDateEntry['id'], $this->firesale->cache_time);
				$product['description'] = strip_tags($product['description']);
				$product['arrival_booking'] = $earliest_arrival_date['2'].'/'.$earliest_arrival_date['1'].'/'.$earliest_arrival_date['0'];
				$product['departure_date'] = $departure_date['2'].'/'.$departure_date['1'].'/'.$departure_date['0'];
				$product['length_of_stay'] = $arrivalDateEntry['length_of_stay'];
				$product['booking_price'] = $arrivalDateEntry['booking_price'];
				if ($arrivalDateEntry['booking_date'] == $start_date && $arrivalDateEntry['length_of_stay'] == $length_of_stay ) {
					$product['close_match'] = '';
				}else{
					$product['close_match'] = 'close match';
				}
				if ($arrivalDateEntry['late_arrivals']) {
					$product['late_arrivals'] = $arrivalDateEntry['late_arrivals'];
				}else{
					$product['late_arrivals'] = '';
				}

				$products[] = $product;
			}
		}

		return $products;
	}

	public function getPrice($arrival, $departure, $prod_id)
	{
		$this->db->select('firesale_bookings.booking_price');
		$this->db->where('firesale_bookings.booking_date BETWEEN DATE_ADD("'.$arrival.'",INTERVAL 1 DAY) and "'.$departure.'"');
		$this->db->where('firesale_bookings.booking_product = '.$prod_id);
		$total_price_query = $this->db->get('firesale_bookings');
		$total_price_results = $total_price_query->result_array();

		$tot_price = 0;
		foreach ($total_price_results as $t => $p) {
			$tot_price = $tot_price + $p['booking_price'];
		}

		return $tot_price;
	}

	public function lateAvailibilites($prod_id, $start_date, $late_date, $departure_date, $variations)
	{
		if ($start_date == date('Y-m-d')) {
			$start_date = date('Y-m-d',strtotime($start_date . '+ 1 days'));
		}

		$booking_status_arrival = array('0', '2', '3');
		$this->db->select('firesale_bookings.booking_price, firesale_bookings.booking_date, firesale_bookings.booking_status');
		$this->db->where('firesale_bookings.booking_date BETWEEN "'.$start_date.'" and "'.$late_date.'"');
		$this->db->where('firesale_bookings.booking_product = '.$prod_id);
		$this->db->where_in('firesale_bookings.booking_status', $booking_status_arrival);
		$this->db->order_by('firesale_bookings.booking_date', 'asc');
		$late_availibility_query = $this->db->get('firesale_bookings');
		$late_availibilites = $late_availibility_query->result_array();

		$count = count($late_availibilites);


		if ($count > 3) {
			$total_price = 0;
			$i=1;
			foreach ($late_availibilites as $key => $value) {
				if ($i <= 3) {

					$total_price = $total_price + $value['booking_price'];
				}
				$i++;
			}

			foreach ($variations as $k => &$v) {
				$explodedkey = explode(" ", $v['var_title']);
				if ($explodedkey['0'] == '3')
				{
					$total_price = number_format($total_price * $v['var_price'], 2);

	// continue;
				}
			}

	//echo $total_price.'<br>';

			$string = 'Late availability from £'.$total_price.' for 3 nights. Contact us to arrange a booking';
		}else{
			$string = '';
		}

	//exit;

		return $string;

	}

}
