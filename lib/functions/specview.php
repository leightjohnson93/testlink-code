<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/ 
 * @filesource $RCSfile: specview.php,v $
 * @version $Revision: 1.10 $ $Author: franciscom $
 * @modified $Date: 2008/09/02 16:39:49 $
 *
 * @author 	Francisco Mancardi (francisco.mancardi@gmail.com)
 *
 * rev:
 *     20080811 - franciscom - BUGID 1650 (REQ)
 *                             documentation improvements
 *
 *     20080528 - franciscom - internal bug - only one version was retrieved
 *     20080510 - franciscom - added getFilteredLinkedVersions()
 *                                   keywordFilteredSpecView()
 *
 *     20080422 - franciscom - BUGID 1497
 *     Suggested by Martin Havlat execution order will be set to external_id * 10
 *     for test cases not linked yet
 *           
 **/ 

/*
arguments:
          spec_view_type: can get one of the following values:
                          'testproject','testplan'
                          
                          This setting change the processing done 
                          to get the keywords.
                          And indicates the type of id (testproject/testplan) 
                          contained in the argument tobj_id.

         tobj_id
         
         id: node id

         name:
         linked_items:  map where key=testcase_id
                        value map with following keys:
                              [testsuite_id] => 2732            
                              [tc_id] => 2733        
                              [z] => 100  ---> nodes_hierarchy.order             
                              [name] => TC1          
                              [tcversion_id] => 2734 
                              [feature_id] => 9      --->> testplan_tcversions.ID
                              [execution_order] => 10
                              [version] => 1         
                              [active] => 1          
                              [external_id] => 1     
                              [exec_id] => 1         
                              [tcversion_number] => 1
                              [executed] => 2734     
                              [exec_on_tplan] => 2735
                              [user_id] =>           
                              [type] =>              
                              [status] =>            
                              [assigner_id] =>       
                              [urgency] => 2         
                              [exec_status] => b     
                        
                        
                        
         map_node_tccount,
                            
         [keyword_id] default 0
         [tcase_id] default null, can be an array
			   [write_button_only_if_linked] default 0
                        
                        
         [do_prune]: default 0. 
                     Useful when working on spec_view_type='testplan'.
                     1 -> will return only linked tcversion
                     0 -> returns all test cases specs. 
                        
                        
                        
                        
returns: array where every element is an associative array with the following
         structure: (to get last updated info add debug code and print_r returned value)
        
         [testsuite] => Array( [id] => 28
                               [name] => TS1 )

         [testcases] => Array(  [2736] => Array
                                (
                                    [id] => 2736
                                    [name] => TC2
                                    [tcversions] => Array
                                        (
                                            [2738] => 2   // key=tcversion id,value=version
                                            [2737] => 1
                                        )

                                    [tcversions_active_status] => Array
                                        (
                                            [2738] => 1  // key=tcversion id,value=active status
                                            [2737] => 1
                                        )

                                    [tcversions_execution_type] => Array
                                        (
                                            [2738] => 1
                                            [2737] => 2
                                        )

                                    [tcversions_qty] => 2
                                    [linked_version_id] => 2737
                                    [executed] => no
                                    [user_id] => 0       ---> !=0 if execution has been assigned
                                    [feature_id] => 12   ---> testplan_tcversions.id
                                    [execution_order] => 20
                                    [external_id] => 2
                                )

                               [81] => Array( [id] => 81
                                             [name] => TC88)
                                             ...
                                             )

       [level] =  
       [write_buttons] => yes or no

       level and write_buttons are used to generate the user interface
       
       
       Warning:
       if the root element of the spec_view, has 0 test => then the default
       structure is returned ( $result = array('spec_view'=>array(), 'num_tc' => 0))


20070707 - franciscom - BUGID 921 - problems with display order in execution screen

20070630 - franciscom
added new logic to include in for inactive test cases, testcase version id.
This is needed to show testcases linked to testplans, but after be linked to
test plan, has been set to inactive on test project.

20061105 - franciscom
added new data on output: [tcversions_qty] 
                          used in the logic to filter out inactive tcversions,
                          and inactive test cases.
                          Counts the quantity of active versions of a test case.
                          If 0 => test case is considered INACTIVE
                                          
       
*/
function gen_spec_view(&$db,$spec_view_type='testproject',
                            $tobj_id,$id,$name,&$linked_items,
                            $map_node_tccount,
                            $keyword_id = 0,$tcase_id = null,
							              $write_button_only_if_linked = 0,$do_prune=0,$add_custom_fields=0)
{
	$write_status = 'yes';
	if($write_button_only_if_linked)
		$write_status = 'no';
	
	$result = array('spec_view'=>array(), 'num_tc' => 0, 'has_linked_items' => 0);
	
	$out = array(); 
	$a_tcid = array();
	
	$tcase_mgr = new testcase($db); 
	$tproject_mgr = new testproject($db);
	
	$hash_descr_id = $tcase_mgr->tree_manager->get_available_node_types();
	$tcase_node_type = $hash_descr_id['testcase'];
	$hash_id_descr = array_flip($hash_descr_id);

	$test_spec = $tproject_mgr->get_subtree($id);
     
	// ---------------------------------------------------------------------------------------------
  // filters
	if($keyword_id)
	{
	    switch ($spec_view_type)
	    {
			case 'testproject':
				$tobj_mgr = &$tproject_mgr;
				break;  
				
			case 'testplan':
				$tobj_mgr = new testplan($db); 
				break;  
	    }
	    $tck_map = $tobj_mgr->get_keywords_tcases($tobj_id,$keyword_id);
	   
	    // Get the Test Cases that has the Keyword_id
	    // filter the test_spec
	    foreach($test_spec as $key => $node)
	    {
		    if($node['node_type_id'] == $tcase_node_type && !isset($tck_map[$node['id']]) )
			   $test_spec[$key]=null;            
	    }
	    $tobj_mgr=null;
	}
  // ---------------------------------------------------------------------------------------------
  
	// ---------------------------------------------------------------------------------------------
	if(!is_null($tcase_id))
	{
	  $testCaseSet = is_array($tcase_id) ? $tcase_id : array($tcase_id);
	  
		// filter the test_spec
		foreach($test_spec as $key => $node)
		{
		  // 20080510 - franciscom
			// if($node['node_type_id'] == $tcase_node_type &&  $node['id'] != $tcase_id )
			if($node['node_type_id'] == $tcase_node_type &&  !in_array($node['id'],$testCaseSet) )
				$test_spec[$key]=null;            
		}
	}
  // ---------------------------------------------------------------------------------------------
    
    $idx = 0;
    $a_tcid = array();
    $a_tsuite_idx = array();
  	$hash_id_pos[$id] = $idx;
  	$out[$idx]['testsuite'] = array('id' => $id, 'name' => $name);
  	$out[$idx]['testcases'] = array();
  	$out[$idx]['write_buttons'] = 'no';
  	
  	$out[$idx]['testcase_qty'] = 0;
  	$out[$idx]['level'] = 1;

    $idx++;
  	if(count($test_spec))
  	{
  		$pivot = $test_spec[0];
  		$the_level = 2;
  		$level = array();
  
  		foreach ($test_spec as $current)
  		{
  			if(is_null($current))
  				continue;
  				
  			if($hash_id_descr[$current['node_type_id']] == "testcase")
  			{
  				$tc_id = $current['id'];
  				$parent_idx = $hash_id_pos[$current['parent_id']];
  				$a_tsuite_idx[$tc_id] = $parent_idx;
  				
  				$out[$parent_idx]['testcases'][$tc_id] = array('id' => $tc_id,'name' => $current['name']);
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions'] = array();
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions_active_status'] = array();

  				// 20080811 - franciscom - BUGID 1650 (REQ)
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions_execution_type'] = array();
  				
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions_qty'] = 0;
  				$out[$parent_idx]['testcases'][$tc_id]['linked_version_id'] = 0;
  				$out[$parent_idx]['testcases'][$tc_id]['executed'] = 'no';

  				$out[$parent_idx]['write_buttons'] = $write_status;
  				$out[$parent_idx]['testcase_qty']++;
  				$out[$parent_idx]['linked_testcase_qty'] = 0;
  				
  				// useful for tc_exec_assignment.php          
  				$out[$parent_idx]['testcases'][$tc_id]['user_id'] = 0;
  				$out[$parent_idx]['testcases'][$tc_id]['feature_id'] = 0;
  				
  				$a_tcid[] = $current['id'];
  			}
  			else
  			{
  				if($pivot['parent_id'] != $current['parent_id'])
  				{
  					if ($pivot['id'] == $current['parent_id'])
  					{
  						$the_level++;
  						$level[$current['parent_id']] = $the_level;
  					}
  					else 
  						$the_level = $level[$current['parent_id']];
  				}
  	            
  	      $out[$idx]['testsuite']=array('id' => $current['id'], 'name' => $current['name']);
  				$out[$idx]['testcases'] = array();
  				$out[$idx]['testcase_qty'] = 0;
  				$out[$idx]['linked_testcase_qty'] = 0;
  				$out[$idx]['level'] = $the_level;
  				$out[$idx]['write_buttons'] = 'no';
  				$hash_id_pos[$current['id']] = $idx;
  				$idx++;
  				    
  				// update pivot.
  				$level[$current['parent_id']] = $the_level;
  				$pivot = $current;
  		    }
  		} // foreach
	} // count($test_spec))

	if(!is_null($map_node_tccount))
	{
		foreach($out as $key => $elem)
		{
			if(isset($map_node_tccount[$elem['testsuite']['id']]) &&
				$map_node_tccount[$elem['testsuite']['id']]['testcount'] == 0)  
				{
				  $out[$key]=null;
				}
			}
	}
	
	
  // and now ???
	if( !is_null($out[0]) )
	{
	  $result['has_linked_items'] = 0;
    if(count($a_tcid))
    {
  		$tcase_set = $tcase_mgr->get_by_id($a_tcid,TC_ALL_VERSIONS);
  		$result['num_tc']=0;
  		$pivot_id=-1;

  		foreach($tcase_set as $the_k => $the_tc)
    	{
			  $tc_id = $the_tc['testcase_id'];
  		  if($pivot_id != $tc_id )
  		  {
  		    $pivot_id=$tc_id;
  		    $result['num_tc']++;
  		  }
  			$parent_idx = $a_tsuite_idx[$tc_id];
  		
        // --------------------------------------------------------------------------
        if($the_tc['active'] == 1)
        {       
  	      if( !isset($out[$parent_idx]['testcases'][$tc_id]['execution_order']) )
  	      {
              // Doing this I will set order for test cases that still are not linked.
              // But Because I loop over all version (linked and not) if I always write, 
              // will overwrite right execution order of linked tcversion.
              //
              // N.B.:
              // As suggested by Martin Havlat order will be set to external_id * 10
  	          $out[$parent_idx]['testcases'][$tc_id]['execution_order'] = $the_tc['tc_external_id']*10;
          } 
    			$out[$parent_idx]['testcases'][$tc_id]['tcversions'][$the_tc['id']] = $the_tc['version'];
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions_active_status'][$the_tc['id']] = 1;
          $out[$parent_idx]['testcases'][$tc_id]['external_id'] = $the_tc['tc_external_id'];
  				  
  				// 20080811 - franciscom - BUGID 1650
  				$out[$parent_idx]['testcases'][$tc_id]['tcversions_execution_type'][$the_tc['id']] = $the_tc['execution_type'];
            
  				  
		    	if (isset($out[$parent_idx]['testcases'][$tc_id]['tcversions_qty']))  
				     $out[$parent_idx]['testcases'][$tc_id]['tcversions_qty']++;
			    else
				     $out[$parent_idx]['testcases'][$tc_id]['tcversions_qty'] = 1;
        }
        
  			if(!is_null($linked_items))
  			{
  				foreach($linked_items as $linked_testcase)
  				{
  					if(($linked_testcase['tc_id'] == $the_tc['testcase_id']) &&
  						($linked_testcase['tcversion_id'] == $the_tc['id']) )
  					{
       				if( !isset($out[$parent_idx]['testcases'][$tc_id]['tcversions'][$the_tc['id']]) )
       				{
        				$out[$parent_idx]['testcases'][$tc_id]['tcversions'][$the_tc['id']] = $the_tc['version'];
  	    			  $out[$parent_idx]['testcases'][$tc_id]['tcversions_active_status'][$the_tc['id']] = 0;
                $out[$parent_idx]['testcases'][$tc_id]['external_id'] = $the_tc['tc_external_id'];

                // 20080811 - franciscom - BUGID 1650 (REQ)
  	    			  $out[$parent_idx]['testcases'][$tc_id]['tcversions_execution_type'][$the_tc['id']] = $the_tc['execution_type'];
				      }
  						$out[$parent_idx]['testcases'][$tc_id]['linked_version_id'] = $linked_testcase['tcversion_id'];
              
              // 20080401 - franciscom
              $exec_order= isset($linked_testcase['execution_order'])? $linked_testcase['execution_order']:0;
              $out[$parent_idx]['testcases'][$tc_id]['execution_order'] = $exec_order;
              
  						$out[$parent_idx]['write_buttons'] = 'yes';
  						$out[$parent_idx]['linked_testcase_qty']++;
  						
  						$result['has_linked_items'] = 1;
  						
  						if(intval($linked_testcase['executed']))
  							$out[$parent_idx]['testcases'][$tc_id]['executed']='yes';
  						
  						if( isset($linked_testcase['user_id']))
  							$out[$parent_idx]['testcases'][$tc_id]['user_id']=intval($linked_testcase['user_id']);
  						if( isset($linked_testcase['feature_id']))
  							$out[$parent_idx]['testcases'][$tc_id]['feature_id']=intval($linked_testcase['feature_id']);
  						break;
  					}
  				}
  			} 
  		} //foreach($tcase_set
  	} 
  	$result['spec_view'] = $out;
  	
	} // !is_null($out[0])
	
	// --------------------------------------------------------------------------------------------
	$out=null;
	if( count($result['spec_view']) > 0 && $do_prune)
	{                                                
	  foreach($result['spec_view'] as $key => $value)
	  {
	    if( isset($value['linked_testcase_qty']) && $value['linked_testcase_qty']== 0)
	    {
	        unset($result['spec_view'][$key]);
	    } 
	  }
	  
    foreach($result['spec_view'] as $key => $value) 
    {
      if( !is_null($value) )
      {
         if( isset($value['testcases']) && count($value['testcases']) > 0 )
         {
           foreach($value['testcases'] as $skey => $svalue)
           {
             if( $svalue['linked_version_id'] == 0)
             {
               unset($result['spec_view'][$key]['testcases'][$skey]);
             }
           }
         } 
         
      } // is_null($value)
    }
	}
	// --------------------------------------------------------------------------------------------

	// --------------------------------------------------------------------------------------------
	// BUGID 1650 (REQ)
	if( count($result['spec_view']) > 0 && $add_custom_fields)
	{                          
	  // Important:
	  // testplan_tcversions.id value, that is used to link to manage custom fields that are used
	  // during testplan_design is present on key 'feature_id' (only is linked_version_id != 0)
    foreach($result['spec_view'] as $key => $value) 
    {
      if( !is_null($value) )
      {
         if( isset($value['testcases']) && count($value['testcases']) > 0 )
         {
           foreach($value['testcases'] as $skey => $svalue)
           {
          
             $linked_version_id=$svalue['linked_version_id'];
             $result['spec_view'][$key]['testcases'][$skey]['custom_fields']='';
            
             // if( $linked_version_id != 0 && 
             //     $svalue['tcversions_execution_type'][$linked_version_id]==TESTCASE_EXECUTION_TYPE_AUTO
             //   )
             //   
             if( $linked_version_id != 0  )
             {
               $cf_name_suffix="_" . $svalue['feature_id'];
               $cf_map=$tcase_mgr->html_table_of_custom_field_inputs($linked_version_id,null,'testplan_design',
                                                                     $cf_name_suffix,$svalue['feature_id']);
               $result['spec_view'][$key]['testcases'][$skey]['custom_fields']=$cf_map;
             }
           }
         } 
         
      } // is_null($value)
    }
  }
	// --------------------------------------------------------------------------------------------

	return $result;
}


/*
  function: 

  args :
  
  returns: 

*/
function getFilteredLinkedVersions(&$argsObj,&$tplanMgr,&$tcaseMgr)
{
    define('DONT_FILTER_BY_TCASE_ID',null);
    $doFilterByKeyword=(!is_null($argsObj->keyword_id) && $argsObj->keyword_id > 0) ? true : false;

    // Multiple step algoritm to apply keyword filter on type=AND
    // get_linked_tcversions filters by keyword ALWAYS in OR mode.
    $tplan_tcases = $tplanMgr->get_linked_tcversions($argsObj->tplan_id,DONT_FILTER_BY_TCASE_ID,
                                                     $argsObj->keyword_id);

    if($doFilterByKeyword && $argsObj->keywordsFilterType == 'AND')
    {
      $filteredSet=$tcaseMgr->filterByKeyword(array_keys($tplan_tcases),
                                              $argsObj->keyword_id,$argsObj->keywordsFilterType);

      $testCaseSet=array_keys($filteredSet);   
      $tplan_tcases = $tplanMgr->get_linked_tcversions($argsObj->tplan_id,$testCaseSet);
    }
    return $tplan_tcases; 
}


/*
  function: 

  args :
  
  returns: 

*/
function keywordFilteredSpecView(&$dbHandler,&$argsObj,$map_node_tccount,
                                 $keywordsFilter,&$tplanMgr,&$tcaseMgr)
{
    $tsuiteMgr = new testsuite($dbHandler); 
	  $tprojectMgr = new testproject($dbHandler); 
	  $tsuite_data = $tsuiteMgr->get_by_id($argsObj->id);
		
		// BUGID 1041
		$tplan_linked_tcversions=$tplanMgr->get_linked_tcversions($argsObj->tplan_id,FILTER_BY_TC_OFF,
		                                                          $argsObj->keyword_id,FILTER_BY_EXECUTE_STATUS_OFF,
		                                                          $argsObj->assigned_to);

		// This does filter on keywords ALWAYS in OR mode.
		$tplan_linked_tcversions = getFilteredLinkedVersions($argsObj,$tplanMgr,$tcaseMgr);

		// With this pieces we implement the AND type of keyword filter.
		$testCaseSet=null;
    if( !is_null($keywordsFilter) )
		{ 
		    $keywordsTestCases=$tprojectMgr->get_keywords_tcases($argsObj->tproject_id,
		                                                          $keywordsFilter->items,$keywordsFilter->type);
		    $testCaseSet=array_keys($keywordsTestCases);
    }
		$out = gen_spec_view($dbHandler,'testplan',$argsObj->tplan_id,$argsObj->id,$tsuite_data['name'],
                         $tplan_linked_tcversions,
                         $map_node_tccount,
                         $argsObj->keyword_id,$testCaseSet,WRITE_BUTTON_ONLY_IF_LINKED);

    return $out;
}

?>