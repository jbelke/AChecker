<?php
/************************************************************************/
/* AChecker                                                             */
/************************************************************************/
/* Copyright (c) 2008 by Greg Gay, Cindy Li                             */
/* Adaptive Technology Resource Centre / University of Toronto          */
/*                                                                      */
/* This program is free software. You can redistribute it and/or        */
/* modify it under the terms of the GNU General Public License          */
/* as published by the Free Software Foundation.                        */
/************************************************************************/

/**
* RESTWebServiceOutput
* Class to generate error report in REST format 
* @access	public
* @author	Cindy Qi Li
* @package checker
*/
if (!defined("AC_INCLUDE_PATH")) die("Error: AC_INCLUDE_PATH is not defined.");

include_once(AC_INCLUDE_PATH.'classes/DAO/ChecksDAO.class.php');
include_once(AC_INCLUDE_PATH.'classes/DAO/UserLinksDAO.class.php');
include_once(AC_INCLUDE_PATH.'classes/DAO/GuidelinesDAO.class.php');
include_once(AC_INCLUDE_PATH.'classes/DAO/UserDecisionsDAO.class.php');

class RESTWebServiceOutput {

	// all private
	var $errors;                    // parameter. array of errors
	var $userLinkID;                // parameter. user_links.user_link_id
	var $guidelineArray;            // parameter. array of guidelines
	
	var $output;                    // final web service output
	
	// REST templates
	var $rest_main =
'<summary>
	<status>{STATUS}<stauts>
	<sessionID>{SESSIONID}<sessionID>
	<NumOfErrors>{NUMOFERRORS}</NumOfErrors>
	<NumOfLikelyProblems>{NUMOFLIKELYPROBLEMS}</NumOfLikelyProblems>
	<NumOfPotentialProblems>{NUMOFPOTENTIALPROBLEMS}</NumOfPotentialProblems>

	<guidelines>
{GUIDELINES}
	</guidelines>
</summary>

<results>
{RESULTS}
</results>
';
	
	var $rest_guideline =
'		<guideline>{GUIDELINE}</guideline>
';
	
	var $rest_result = 
'	<result>
		<resultType>{RESULTTYPE}</resultType>
		<lineNum>{LINENUM}</lineNum>
		<columnNum>{COLUMNNUM}</columnNum>
		<errorMsg>&lt;a href=&quot;{BASE_HREF}checker/suggestion.php?id={CHECK_ID}&quot;
               onclick=&quot;popup(\'{BASE_HREF}checker/suggestion.php?id={CHECK_ID}\'); return false;&quot; 
               title=&quot;{TITLE}&quot; target=&quot;_new&quot;&gt;{ERRORMSG}</a>
        </errorMsg>
		<errorSourceCode>{ERRORSOURCECODE}</errorSourceCode>
		{REPAIR}
		{DECISION}
	</result> 
';
	
	var $rest_repair = '<repair>{REPAIR}</repair>';
	
	var $rest_decision_questions =
'<sequenceID>{SEQUENCEID}</sequenceID>
        <decisionPass>{DECISIONPASS}</decisionPass>
		<decisionFail>{DECISIONFAIL}</decisionFail>
';
	
	var $rest_decision_made =
'		<decisionMade>{DECISIONMADE}</decisionMade>
		<decisionMadeDate>{DECISIONMADEDATE}</decisionMadeDate>
';
	
	/**
	* public
	* $errors: an array, output of AccessibilityValidator -> getValidationErrorRpt
	* $user_link_id: user link id
	* $guideline_array: an array of guideline ids
	*/
	function RESTWebServiceOutput($errors, $userLinkID, $guidelineArray)
	{
//		debug($errors);exit;
		$this->errors = $errors;
		$this->userLinkID = $userLinkID;
		$this->guidelineArray = $guidelineArray;
		
		$this->generateRESTRpt();
	}
	
	/**
	* private
	* main process to generate report in html format
	*/
	private function generateRESTRpt()
	{
		$num_of_errors = 0;
		$num_of_likely_problems = 0;
		$num_of_potential_problems = 0;
		
		$checksDAO = new ChecksDAO();
		$userDecisionsDAO = new UserDecisionsDAO();
		
		// generate section details
		foreach ($this->errors as $error)
		{ // generate each error result
			$result_type = '';
			$repair = '';
			$decision = '';
			$decision_questions = '';
			$decision_made = '';
			
			$row_check = $checksDAO->getCheckByID($error["check_id"]);

			if ($row_check["confidence"] == KNOWN)
			{ // only known errors have <repair> 
				$num_of_errors++;
				$result_type = _AC('error');
				
				$repair = str_replace('{REPAIR}', 
				                      htmlspecialchars(_AC($row_check["how_to_repair"]), ENT_QUOTES), 
				                      $this->rest_repair);
			}
			else 
			{
				// generate user's decision. only likely and potential problems have decisions to make
				$row_userDecision = $userDecisionsDAO->getByUserLinkIDAndLineNumAndColNumAndCheckID($this->userLinkID, $error["line_number"], $error["col_number"], $error['check_id']);
				
				if ($row_userDecision['decision'] == AC_DECISION_PASS || $row_userDecision['decision'] == AC_DECISION_FAIL)
				{
					if ($row_userDecision['decision'] == AC_DECISION_PASS) $decision_text = _AC('pass');
					if ($row_userDecision['decision'] == AC_DECISION_FAIL) $decision_text = _AC('fail');
					
					$decision_made = str_replace(array('{DECISIONMADE}', 
					                                   '{DECISIONMADEDATE}'),
					                             array($decision_text, 
					                                   $row_userDecision['last_update']),
					                             $this->rest_decision_made);
				}
			
				if ($row_check["confidence"] == LIKELY)
				{
					$result_type = _AC('likely_problem');
					
					if ($row_userDecision['decision'] == AC_NO_DECISION || $row_userDecision['decision'] == AC_DECISION_FAIL) 
						$num_of_likely_problems++;
					
				}

				if ($row_check["confidence"] == POTENTIAL)
				{
					$result_type = _AC('potential_problem');
					
					if ($row_userDecision['decision'] == AC_NO_DECISION || $row_userDecision['decision'] == AC_DECISION_FAIL)
						$num_of_potential_problems++;
					
				}
				
				$decision_questions = str_replace(array('{SEQUENCEID}', '{DECISIONPASS}', '{DECISIONFAIL}'),
				                                  array($row_userDecision['sequence_id'], _AC($row_check['decision_pass']), _AC($row_check['decision_fail'])),
				                                  $this->rest_decision_questions);
				                                  
				$decision = $decision_questions . $decision_made;
				// end of generating user's decision
			}
			
			$result .= str_replace(array('{RESULTTYPE}', 
			                             '{LINENUM}', 
			                             '{COLUMNNUM}', 
			                             '{BASE_HREF}', 
			                             '{CHECK_ID}', 
			                             '{TITLE}',
			                             '{ERRORMSG}',
			                             '{ERRORSOURCECODE}', 
			                             '{REPAIR}', 
			                             '{DECISION}'),
			                      array($result_type, 
			                            $error["line_number"], 
			                            $error["col_number"], 
			                            AC_BASE_HREF, 
			                            $error['check_id'], 
			                            _AC("suggest_improvements"),
			                            htmlspecialchars(_AC($row_check['err']), ENT_QUOTES),
			                            htmlspecialchars($error["html_code"], ENT_QUOTES),
			                            $repair,
			                            $decision),
			                      $this->rest_result);
		}
		
		// retrieve session id
		$userLinksDAO = new UserLinksDAO();
		$row = $userLinksDAO->getByUserLinkID($this->userLinkID);
		$sessionID = $row['last_sessionID'];
		
		// generate guidelines
		$guidelinesDAO = new GuidelinesDAO();
		
		foreach ($this->guidelineArray as $gid)
		{
			$row_guideline = $guidelinesDAO->getGuidelineByIDs($gid);
			$guidelines .= str_replace('{GUIDELINE}', $row_guideline[0]['title'], $this->rest_guideline);
		}
		
		// find out result status: pass, fail, conditional pass
		if ($num_of_errors > 0)
		{
			$status = _AC('fail');
		}
		else if ($num_of_likely_problems + $num_of_potential_problems > 0)
		{
			$status = _AC('conditional_pass');
		}
		else
		{
			$status = _AC('pass');
		}
		
		// generate final output
		$this->output = str_replace(array('{STATUS}', 
		                                  '{SESSIONID}', 
				                          '{NUMOFERRORS}', 
		                                  '{NUMOFLIKELYPROBLEMS}', 
		                                  '{NUMOFPOTENTIALPROBLEMS}', 
		                                  '{GUIDELINES}',
		                                  '{RESULTS}'),
		                            array($status,
		                                  $sessionID,
		                                  $num_of_errors,
		                                  $num_of_likely_problems,
		                                  $num_of_potential_problems,
		                                  $guidelines,
		                                  $result), 
		                            $this->rest_main);
	}
	
	/** 
	* public
	* return final web service output
	* parameters: none
	* author: Cindy Qi Li
	*/
	public function getWebServiceOutput()
	{
		return $this->output;
	}
	
	/** 
	* public
	* return error report in html
	* parameters: $errors: errors array
	* author: Cindy Qi Li
	*/
	public static function generateErrorRpt($errors)
	{
		// initialize error codes. Note that all errors reported in REST need to be defined here.
		$errorCodes['AC_ERROR_EMPTY_URI'] = 401;
		$errorCodes['AC_ERROR_INVALID_URI'] = 402;
		$errorCodes['AC_ERROR_EMPTY_WEB_SERVICE_ID'] = 403;
		$errorCodes['AC_ERROR_INVALID_WEB_SERVICE_ID'] = 404;
		$errorCodes['AC_ERROR_SEQUENCEID_NOT_GIVEN'] = 405;
		
		// error template in REST format
		$rest_error = 
'<errors>
	<totalCount>{TOTOAL_COUNT}</totalCount>
{ERROR_DETAIL}
</errors>
';
	
		$rest_error_detail = 
'	<error code="{ERROR_CODE}">
		<message>{MESSAGE}</message>
	</error>
';
		if (!is_array($errors)) return false;
		
		foreach ($errors as $err)
		{
			$error_detail .= str_replace(array("{ERROR_CODE}", "{MESSAGE}"), 
			                             array($errorCodes[$err], _AC($err)), 
			                             $rest_error_detail); 
		}
			                            
		return str_replace(array('{TOTOAL_COUNT}', '{ERROR_DETAIL}'), 
		                   array(count($errors), $error_detail),
		                   $rest_error);
	}

	/** 
	* public
	* return success in REST
	* parameters: none
	* author: Cindy Qi Li
	*/
	public static function generateSuccessRpt()
	{
		$rest_success = 
'<summary>
	<status>success</status>
</summary>
';
		
		return $rest_success;
	}
}
?>  