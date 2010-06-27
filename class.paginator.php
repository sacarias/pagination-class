<?php

/*
 *Paginator, usefull for ANY database table 
 *includes a search function of multiple
 *coloumns dynamically.
 *
 *You must have a database connection for 
 *the paginator to work
 */
class paginator{
	
	//Maximum number of results per page
	var $items_per_page;

	//The current page being viewed
	var $current_page;
	
	//The select part of the query
	var $query;

	//The WHERE clause of a search query
	var $where;

	//The type of operator
	var $type;

	//The results of the query
	var $results = array();

	//Database table we will work with
	var $table; 
	
	//Total items in the database
	var $total_items; 
	
	//Total pages that all the items make up
	var $total_pages;

	//Specifys wich page to start at
	var $start; 
	
	//The info for the navigation 
	//will be held in this car
	var $nav = array();

	//Search query user types in
	var $string;
	
	//what we want to return 
	//ie. "*", or id
	var $distinct;
	
	//The url vars that go in the
	//url to continue to the next pages
	var $url;
	
	//The main query results
	var $run;
	
	//The main query but without the limit
	var $pre;
	
	var $startTime;
	
	var $timeEnd;
	
	var $duration;
	

	function __construct(){	
		$this->setItemsPerPage();
		$this->setSearchString();
		$this->setCurrentPage();
		
	}
	
	/*
	 * setSearchString - this function gets
	 * the search query that the user types in
	 * if there is one.
	 */
	function setSearchString(){
		if(isset($_GET['q'])){
			$this->string = mysql_real_escape_string(trim($_GET['q']));
		}else{
			$this->string = "";
		}
	}
	
	/*
	 * setDistinct - This function
	 * sets the return cols that the query
	 * shall return. e.g: "*", or "user_id"
	 */
	function setDistinct($distinct){
		$this->distinct = $distinct;
	}
	
	/*
	 * makeNav - This function calculates
	 * and builds the navigation links
	 * neccessary to switch pages.
	 */
	function makeNav(){
		
		if ($this->current_page > 1){
            $this->nav["previous"] = ($this->current_page - 1);
        }	

        if ($this->current_page > 2){
            $this->nav["first"] = 1;
        }

        for ($i = 4; $i > 0; $i--){
        	
            if (($this->current_page - $i) > 0){
                $this->index = $this->current_page - $i;
                $this->nav["left"][] = array("page" => $this->index);
            }
        }

        $this->nav["current"] = $this->current_page;

        for ($i = 0; $i < 4; $i++){
            if (($this->current_page + 1 + $i) <= $this->total_pages){
                $this->index = ($this->current_page + 1) + $i;
                $this->nav["right"][] = array("page" => $this->index);
                
            }
        }

        if ($this->current_page < $this->total_pages - 1){
            $this->nav["last"] = $this->total_pages;
        }

        if ($this->current_page < $this->total_pages){
            $this->nav["next"] = ($this->current_page + 1);
        }
	}
	
	/*
	 * setTable - This function sets the
	 * table we will be working with in 
	 * out sql query
	 */
	function setTable($table = ""){
		$this->table = $table;
	}
	
	/*
	 * setItemsPerPage - Default will allow 10 items
	 * per page to be shown, otherwise it will show
	 * the provided number of items per page.
	 */
	function setItemsPerPage($value = 10){
		if(isset($_GET['perPage']) && is_numeric($_GET['perPage'])){
			$this->items_per_page = $_GET['perPage'];
		}else{
			$this->items_per_page = $value;
		}
	}
	
	/*
	 * setCurrentPage - This function sets the current
	 * page to 1 if no page is specified,
	 * however if there is a page specified
	 * current page is set to what is specified
	 */
	function setCurrentPage(){
		if(isset($_GET['page']) && is_numeric($_GET['page'])){
			$this->current_page = $_GET['page'];
		}else{
			$this->current_page = 1;
		}
	}
	

	/*
	 * setSearchQuery - This function should
	 * be used to add search functionalyity to 
	 * the paginator.
	 * specify each col in the table that you 
	 * would want a search done in.
	 * 
	 * $cols = array("coulmn1", "coulmn2");
	 * query will be searched for in each field
	 */
	function setSearchQuery($cols = array()){
		if(trim($this->string) !== ""){
			$count = count($cols);
			if($count == 0){
				$this->where = "";
			}else{
				$terms = explode(" ", $this->string);
				$this->where = "WHERE";
				$nWords = count($terms);
				$b = 0;
				for($i=0;$i<$count;$i++){
					for($n=0;$n<$nWords;$n++){
						$st = ($n == 0)? $this->string : $terms[$n];
						$or = ($b == 0)? " " : " OR ";
						$this->where .= $or.$cols[$i]." LIKE '%".$st."%'";
						$b++;
					}
				}
			}
		}else{
			$this->where = "";
		}		
	}
	
	/*
	 * setSpecificCols - This function add additional cols
	 * to search for in the database. if these vars arent
	 * specified in the search, then they will not be searched.
	 * 
	 * $cols = array(
	 * 					array("get"=>"q", "col"=>"quantity"),
	 * 		   			array("get"=>"b", "col"=>"border")
	 * 				);
	 */
	function setSpecificCols($cols = array()){
		
		$count = count($cols);
		
		if($count !== 0){
			
			$this->url = "";
			
			for($i=0;$i<$count;$i++){
				
				if(isset($_GET[$cols[$i]["get"]])){
					
					$wh = ($this->string !== "")? " AND " : " WHERE ";
					$this->where .= $wh.$cols[$i]["col"]." = '".$_GET[$cols[$i]["get"]]."'";
					$this->url .= $cols[$i]["get"]."=".$_GET[$cols[$i]["get"]]."&";
					
				}
				
			}
			
			$this->url = substr($this->url, 0, -1);
			$this->where = trim($this->where);
			
		}else{
			
			$this->where .= "";
			
		}
	}

	/*
	 * buildQuery - Does exactly what it says.
	 */
	function buildQuery(){
		$this->query = "SELECT DISTINCT ".$this->distinct." FROM ".$this->table." ".$this->where." LIMIT ".$this->start.", ".$this->items_per_page;
		$this->pre = "SELECT DISTINCT ".$this->distinct." FROM ".$this->table." ".$this->where;
	}

	/*
	 * runQuery - runs the querys provided
	 * and returns the rows in an array that if 
	 * finds.
	 */
	function runQuery(){
		$this->pre = mysql_query($this->pre);
		$this->run = mysql_query($this->query);
		while($rows = mysql_fetch_assoc($this->run)){
			$this->results[] = $rows;
		}
	}
	
	/*
	 * init - this function puts all the functions
	 * together and to run at once.
	 * it gets the limit, builds the query,
	 * runs the querys, calculates the navigation links
	 * and then builds the navigation.
	 */
	function init(){
		$this->startTime = microtime();
		$this->start = ($this->current_page - 1)*$this->items_per_page;
		$this->buildQuery();
		$this->runQuery();
		$this->total_items = mysql_num_rows($this->pre);
		$this->total_pages = ceil($this->total_items/$this->items_per_page);
		$this->makeNav();
		$this->timeEnd = microtime();
		$this->duration = $this->timeEnd - $this->startTime;
	}

}

/*
 *Initialize the paginator for use
 */
$p = new paginator;

?>

