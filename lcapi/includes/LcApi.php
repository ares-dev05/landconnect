<?php

class LcAPi {

	/**
	 * Enumerate the possible API calls
	 */
	// GETS
	const CALL_GET_COMPANIES		= "getcompanieslist";
	const CALL_GET_COMPANY_DATA		= "getcompanydatasvg";
	const CALL_GET_COMPANY_RANGES   = "getcompanyranges";
	const CALL_GET_HOUSE_DATA       = "gethousedata";
	const CALL_GET_COMPANY_THEME	= "getvisualtheme";
	const CALL_GET_SESSIONS			= "getsessionslist";
	const CALL_GET_ENVLEOPES		= "getenvelopecatalogue";
	// SETS
	const CALL_SAVE_SESSION			= "savesession";
	const CALL_SAVE_ENVELOPE		= "saveenvelope";
	// STATS
	const CALL_STAT					= "recordstatistic";
	const CALL_STAT_HOUSE			= "housestatistic";
	// USER Info
    const CALL_SET_TUTORIAL_STATE   = "settutorialstate";
    const VALIDATE_LOGIN            = "validatelogin";


	static private function validCalls() {
		return array(
			self::CALL_GET_COMPANIES,
			self::CALL_GET_COMPANY_DATA,
			self::CALL_GET_COMPANY_RANGES,
			self::CALL_GET_HOUSE_DATA,
			self::CALL_GET_COMPANY_THEME,
			self::CALL_GET_SESSIONS,
			self::CALL_GET_ENVLEOPES,
			self::CALL_SAVE_SESSION,
			self::CALL_SAVE_ENVELOPE,
			self::CALL_STAT,
			self::CALL_STAT_HOUSE,
            self::CALL_SET_TUTORIAL_STATE,
            self::VALIDATE_LOGIN
		);
	}

	/**
	 * @var $db PDO
	 */
	private $error;

	private $db;
	private $companyData;
	private $stateData;
	private $command;

	private $companyId;
	private $stateId;
	private $userId;

	private $s3Client;


	public function __construct($apiUsage=false)
    {
        $this->db = pdoConnect();

        if (!$apiUsage) {
            if ($this->validateApiCall()) {
                // run
                $this->run();
            } else {
                if ($this->companyData) $cd = "has CD";
                else $cd = "no CD";
                if ($this->stateData) $sd = "has SD";
                else $sd = "no SD";
                // $this->outputSuccess(false, "<msg>um N/A {$this->error} $cd $sd </msg>");
                // $this->outputSuccess(false, "<msg>Unexpected backend error.</msg>");
                // Succeed silently
                $this->outputSuccess(true); // , "<msg>Unexpected backend error.</msg>");
            }
        }
    }

	private function setupS3Client()
	{
		if ( !$this->s3Client ) {
			$this->s3Client = getS3Client();
			// register the stream wrapper so we can easily write to S3
			$this->s3Client->registerStreamWrapper();
		}
	}

	/**
	 * Build the full S3 storage path
	 * @param $object
	 * @return string
	 */
	private function s3Path( $object )
	{
		return "s3://".STORAGE_BUCKET."/".$object;
	}

	/**
	 * @param $key
	 * @param bool $integer
	 * @return mixed
	 */
	private function get( $key, $integer=false ) {
		return isset($_GET[$key]) ?
					( $integer ? intval($_GET[$key]) : $_GET[$key] ) :
					( $integer ? 0 : null );
	}

	/**
	 * @param $key
	 * @param bool $integer
	 * @return mixed
	 */
	private function post( $key, $integer=false ) {
		return isset($_POST[$key]) ?
					( $integer ? intval($_POST[$key]) : $_POST[$key] ) :
					( $integer ? 0 : null );
	}

	/**
	 * Validate that the API call has all the required data
	 * @TODO @SECURITY: make sure that this call is made by an authorized user.
	 * @return bool
	 */
	private function validateApiCall()
	{
		if ( isset($_GET['ckey']) && isset($_GET['command']) && isset($_GET['state']) && ctype_digit($_GET['state']) ) {
			// validate the command
			if ( !in_array(
				$this->command=strtolower($_GET['command']),
				self::validCalls()
			) ) {
				return false;
			}

			// validate the company & state
			try {
				// fetch the company data
				$stmtCompany = $this->db->prepare("SELECT * FROM companies WHERE ckey=:ckey");
				if ( $stmtCompany->execute(array(":ckey"=>$_GET['ckey'])) ) {
					$this->companyData = $stmtCompany->fetchObject();
				}

				// fetch the state data
				$stmtState = $this->db->prepare("SELECT * FROM house_states WHERE id=:id");
				if ( $stmtState->execute(array(":id"=>$_GET['state'])) ) {
					$this->stateData = $stmtState->fetchObject();
				}
			}	catch (Exception $error) { $this->error = $error->getMessage(); }

			// fetch the user ID (if it's set)
			// @TODO @SECURITY: validate this with the logged-in-user cookies
			$this->userId = $this->get( "userId", true );

			// validate the specified company and state
			if ( isset($this->companyData) && isset($this->stateData) ) {
				$this->companyId = $this->companyData->id;
				$this->stateId	 = $this->stateData->id;

				return true;
			}
		}

		return false;
	}

	private function run()
	{
		// setup the Amazon S3 client and the stream wrapper for easy I/O
		$this->setupS3Client();

		switch ($this->command) {
			// @TODO @UNTESTED
			case self::CALL_GET_COMPANIES:
				$this->runGetCompanies();
				break;

			case self::CALL_GET_COMPANY_DATA:
				$this->runGetCompanyData();
				break;

            case self::CALL_GET_COMPANY_RANGES:
                $this->runGetCompanyRanges();
                break;

            case self::CALL_GET_HOUSE_DATA:
                $this->runGetHouseData();
                break;

			case self::CALL_GET_COMPANY_THEME:
				$this->runGetCompanyTheme();
				break;
			case self::CALL_GET_SESSIONS:
				$this->runGetSessions();
				break;
			// @TODO @UNTESTED
			case self::CALL_GET_ENVLEOPES:
				$this->runGetEnvelopes();
				break;
			case self::CALL_SAVE_SESSION:
				$this->runSaveSession();
				break;
			// @TODO @UNTESTED
			case self::CALL_SAVE_ENVELOPE:
				$this->runSaveEnvelope();
				break;
			case self::CALL_STAT:
				$this->runSaveStatistic();
				break;
			case self::CALL_STAT_HOUSE:
				$this->runSaveHouseStatistic();
				break;

            case self::CALL_SET_TUTORIAL_STATE:
                $this->runSetTutorialState();
                break;

            case self::VALIDATE_LOGIN:
                $this->runValidateLogin();
                break;
		}
	}

	/**
	 * Outputs a generic response XML
	 * @param bool $success
	 */
	private function outputSuccess( $success=true, $content="", $successKey="ok" )
	{
		$this->outputXml( "
			<result {$successKey}='{$success}' >
				{$content}
			</result>
		" );
	}

    /**
     * @param string $data
     */
	private function outputXml($data)
	{
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: inline; filename=data.xml');
        header('Content-Length: ' . strlen($data));
        echo $data;
	}


	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// API Calls: Stats

	/**
	 * Record an application statistic to the DB
	 */
	private function runSaveStatistic()
	{
	    if (isset($_GET['statkey']) && isset($_GET['statvalue'])) {
            // output a generic response XML indicating success
            $this->outputSuccess(
                $this->db->prepare(
                    "INSERT INTO statistics SET uid=:uid, cid=:cid, s_key=:skey, s_value=:svalue, `date`=NOW()"
                )->execute(array(
                    ":uid" => $this->userId,
                    ":cid" => $this->companyId,
                    ":skey" => $this->get("statkey"),
                    ":svalue" => $this->get("statvalue")
                ))
            );
        }   else {
	        // Ignore statistic storage errors as they're not relevant to the functionality of the application
            $this->outputSuccess();
        }
	}

	/**
	 * Record a house usage statistic to the DB
	 */
	private function runSaveHouseStatistic()
	{
		// execute the house stat query, as we don't want to fail in case of an error
		$success = $this->db->prepare(
			"INSERT INTO statistics_houses
				SET uid=:uid, cid=:cid, trig=:trig, house=:house, facade=:facade, options=:options, `date`=NOW()"
		)	->execute( array(
			// used globally
			":uid"		=> $this->userId,
			":cid"		=> $this->companyId,
			":trig"		=> $this->get( "trigger" ),
			":house"	=> $this->get( "house" ),
			// used for the overall stats
			":facade"	=> $this->get( "facade" ),
			":options"	=> $this->get( "options" )
		) );
		// prepare the breakdown statistics: facades + options
		$stmtBreakdown = $this->db->prepare(
			"INSERT INTO statistics_breakdown
				SET uid=:uid, cid=:cid, trig=:trig, house=:house, `type`=:btype, `name`=:bname, `date`=NOW()"
		);
		// Store a breakdown stat for the facade
		$success &= $stmtBreakdown->execute( array(
			// used globally
			":uid"		=> $this->userId,
			":cid"		=> $this->companyId,
			":trig"		=> $this->get( "trigger" ),
			":house"	=> $this->get( "house" ),
			// used for the breakdown
			":btype"	=> "facade",
			":bname"	=> $this->get( "facade" )
		) );
		// Store breakdown stats for the Options (if any)
		$options = $this->get("options");
		foreach ( explode("|", $options) as $option ) {
			$success &= $stmtBreakdown->execute( array(
				// used globally
				":uid"		=> $this->userId,
				":cid"		=> $this->companyId,
				":trig"		=> $this->get( "trigger" ),
				":house"	=> $this->get( "house" ),
				// used for the breakdown
				":btype"	=> "option",
				":bname"	=> $option
			) );
		}

		// output a generic response XML indicating success
		$this->outputSuccess( $success );
	}

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // MISC / Batch

    /**
	 * Enumerate all the companies in the database; used by bots only
	 * @DISABLED @WARNING @SECURITY @UNTESTED: this allows an intruder to enumerate all the companies and their access keys,
	 * 		which would then allow them to run command on behalf of any user in any company.
	 */
	private function runGetCompanies()
	{
		if ( $this->companyData->use_as_bot ) {
			$stmt = $this->db->query( "SELECT * FROM companies WHERE use_as_bot=0 AND `type`='builder'" );

			$companies = array();
			while ( ($cobj=$stmt->fetchObject()) !== NULL ) {
				try {
					$themeId = intval( is_numeric($cobj->theme_id) ?
						$cobj->theme_id :
						json_decode($cobj->theme_id, true)[$this->stateId]
					);
				}	catch (Exception $e) {
					$themeId = 1;
				}

				$companies[]= "<company id='{$cobj->id}' key='{$cobj->ckey}' name='{$cobj->name}' themeid='{$themeId}' builderid='{$cobj->builder_id}' />";
			}

			$this->outputXml("
				<companies>
					".join('\n', $companies)."
				</companies>
			");
		}
	}

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // User Data: Sessions/Properties

	/**
	 * Enumerate a user's saved sessions
	 * @UPGRADE @OPTIMIZE: do we really need to re-generate this all the time?
	 * 		Also - let's not call this whenever the user opens the 'load' dialog, but instead cache and update locally
	 * 		saved sessions
	 */
	private function runGetSessions()
	{
		// prepare the query
		$stmt = $this->db->prepare(
			"SELECT * FROM sitting_sessions
				WHERE cid=:cid AND uid=:uid AND
					  CHAR_LENGTH(data)>0 AND
					  `date` >= (CURDATE() - INTERVAL 12 MONTH)
				ORDER BY date DESC"
		);

		// build up the sessions list
		$sessionsList = array();
		if ( $stmt->execute(array(":uid"=>$this->userId, ":cid"=>$this->companyId)) ) {
			while ( ($session=$stmt->fetchObject())!=NULL ) {
				// format the info
				$saveName = htmlentities($session->name);
				$saveDate = DateTime::createFromFormat("Y-m-d H:i:s", $session->date)->format('Y/m/d H:i:s');
				// add this session as an XML node
				$sessionsList[]= "
					<session id='{$session->id}' name='{$saveName}' date='{$saveDate}'>
						<![CDATA[{$session->data}]]>
					</session>
				";
			}

			$this->outputXml("
				<sessions uid='{$this->userId}'>
					".join("", $sessionsList)."
				</sessions>
			");
		}	else {
			$this->outputSuccess( false );
		}
	}

	/**
	 * Save a new session or update an existing one. The data is POST-ed in the following format
	 * {
	 * 	   [sessionId]: int
	 * 	   name:		String,
	 * 	   session:		String (created by RestoreUtil.encode(data))
	 * }
	 */
	private function runSaveSession()
	{
		// prepare the query
		$setData = "cid=:cid, uid=:uid, `name`=:name, `data`=:sessionData, `date`=NOW()";
		$stmt	 = $this->db->prepare(
			($sessionId=$this->post("sessionId", true)) > 0 ?
			// as an UPDATE if the sessionId is specified
			"UPDATE sitting_sessions SET {$setData} WHERE id={$sessionId}":
			// as an INSERT if this is a new session
			"INSERT INTO sitting_sessions SET {$setData}"
		);

		if ( $stmt->execute(array(
			":cid"			=> $this->companyId,
			":uid"			=> $this->userId,
			":name"			=> $this->post("name"),
			":sessionData"	=> $this->post("session")
		)) ) {
			if (!$sessionId)
				 $sessionId	= $this->db->lastInsertId();
			$sessionDate	= (new DateTime())->format('Y/m/d H:i:s');

			// output the session details
			$this->outputSuccess( true, "<session id='{$sessionId}' date='{$sessionDate}' />", "success");
		}	else {
			$this->outputSuccess( false, "<msg>A database error occured. Please try again later.</msg>", "success" );
		}
	}

    /**
     * set the Tutorial state for a user
     */
    private function runSetTutorialState()
    {
        // set the new tutorial state with the given values
        $success = $this->db->prepare(
            "INSERT INTO
                user_tutorials ( uid,  tutorial,  state)
                        VALUES (:uid, :tutorial, :state)
             ON DUPLICATE KEY UPDATE
                state=:state2"
        )   ->execute(array(
            ":uid"      => $this->userId,
            ":tutorial" => $this->post("tutorial"),
            ":state"    => $this->post("state"),
            ":state2"   => $this->post("state")
        ));
        // output a generic response XML indicating success
        $this->outputSuccess( $success );
    }

    /**
     * Validate that the calling user is logged in
     */
    private function runValidateLogin()
    {
        if (isUserLoggedIn()) {
            global $loggedInUser;

            if ($loggedInUser && $loggedInUser->user_id===$this->userId) {
                $this->outputSuccess(true);
                return;
            }
        }

        $this->outputSuccess(false);
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Company Data: Envelopes

	/**
	 * Save envelope data to the catalogue
	 * @TODO @SECURITY: confirm that the user doing this action has the required permissions
	 */
	private function runSaveEnvelope()
	{
		// prepare the query
		$setData = "`name`=:name, category=:category, envelope=:envelope, uid=:uid";
		$stmt	 = $this->db->prepare(
			($envelopeId=$this->post("envelopeId", true)) > 0 ?
			// as an UPDATE if the sessionId is specified
			"UPDATE env_catalogue SET $setData WHERE id={$envelopeId}}":
			// as an INSERT if this is a new session
			"INSERT INTO env_catalogue SET cid=:cid, sid=:sid, {$setData}"
		);

		if ( $stmt->execute(array(
			":name"		=> $this->post("name"),
			":category"	=> $this->post("category"),
			":envelope"	=> $this->post("envelope"),
			":cid"		=> $this->companyId,
			":sid"		=> $this->stateId,
			":uid"		=> $this->userId
		)) ) {
			if (!$envelopeId )
				 $envelopeId = pdoConnect()->lastInsertId();

			// delete the envelope catalogue for this company/state.
			$this->refreshEnvelopeCatalogue( );
			$this->outputSuccess( true, "<envelope id='{$envelopeId}' />" );
		}	else {
			$this->outputSuccess( false, "<msg>A database error has occurred. Please try again later.</msg>" );
		}
	}

	/**
	 * outputs the envelopes data for the current company/state of the logged-in user
	 */
	private function runGetEnvelopes()
	{
		if ( intval(getvar('forceRebuild', 0)) ) {
			$this->rebuildEnvelopesXml();
		}
		$this->outputEnvelopesXml();
	}

	/**
	 * outputs the floorplan data for the current company/state of the logged-in user
	 */
	private function runGetCompanyData()
	{
		// check if we have to rebuild it or load an existing one
		$multihouse		= intval(getvar('multihouse', 0));
		$exclusive		= intval(getvar('exclusive', 0));

		if ( intval(getvar('forceRebuild', 0)) ) {
			$this->rebuildCompanyXml( $multihouse, $exclusive );
		}
		$this->outputCompanyXml($multihouse, $exclusive);
	}

    /**
     * Generates a similar output to runGetCompanyData, but without including the XML and area data for the houses
     */
	private function runGetCompanyRanges()
    {
        // check if we have to rebuild it or load an existing one
        $multihouse = intval(getvar('multihouse', 0));
        $exclusive	= intval(getvar('exclusive', 0));

        $this->outputCompanyRanges($multihouse, $exclusive);
    }

    private function runGetHouseData()
    {
        $houseId    = intval(getvar('house', 0));
        $stmtHouse  = $this->db->prepare("
            SELECT house_svgs.*, house_ranges.folder FROM house_svgs
            INNER JOIN house_ranges
            ON house_ranges.id = house_svgs.range_id
            WHERE house_svgs.id=:house_id AND house_ranges.cid=:company_id
		");

        if ($stmtHouse->execute(array(
            ":house_id" => $houseId,
            ":company_id" => $this->companyId
        )) &&
            ($house=$stmtHouse->fetchObject()) !== false) {
            // floorplans/[COMPANY_KEY]/[STATE.ABBREV]/[RANGE.FOLDER]/[HOUSE_NAME].svg
            $houseUrl = $this->s3Path(
                "floorplans/" .
                $this->companyData->ckey . "/" .
                strtolower($this->stateData->abbrev) . "/" .
                strtolower($house->folder) . "/" .
                $house->url
            );

            // remove unused parameters from the house data to reduce load time
            $houseData = str_replace(
                "stroke=\"#000000\" stroke-width=\"0.7086614\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-miterlimit=\"10\"",
                "",
                // fetch the house SVG from Amazon S3
                file_get_contents($houseUrl)
            );

            $houseName = htmlspecialchars($house->name);

            // create the house XML
            $this->outputXml("
<house id='{$house->id}' name='{$houseName}'>
<area><![CDATA[{$house->area_data}]]></area>
<svg><![CDATA[{$houseData}]]></svg>
</house>
            ");
        }   else {
            $this->outputXml("<house id='{$houseId}'></house>");
        }
    }

	/**
	 * outputs the visual theme for the current company/state of the logged-in user
	 */
	private function runGetCompanyTheme()
	{
		$stmt = $this->db->prepare(
			"SELECT * FROM theme_colors
				WHERE tid=:tid
				ORDER BY id"
		);

		// build up the theme entries
		$themeColors = array();
		if ( $stmt->execute(array(":tid"=>intval($_GET['theme']))) ) {
			while ( ($color=$stmt->fetchObject())!=NULL ) {
				$themeColors[]= "
					<color id='{$color->id}' name='{$color->name}' value='{$color->color}' />
				";
			}

			$this->outputXml("
				<theme>
					".join("", $themeColors)."
				</theme>
			");
		}	else {
			$this->outputSuccess( false );
		}
	}

	/**
	 * returns the path to the envelopes data file
	 * @return string
	 */
	private function getEnvelopesCataloguePath( )
	{
		return $this->s3Path(
			"envelopes_cache/catalogue_{$this->companyId}_{$this->stateId}.zip"
		);
	}

	/**
	 * Deletes a builder/state envelope catalogue after a change to an envelope / envelope addition
	 * @param $company
	 * @param $state
	 */
	private function refreshEnvelopeCatalogue()
	{
		@unlink( $this->getEnvelopesCataloguePath() );
	}

	/**
	 * re-creates the envelopes catalogue data file
	 */
	private function rebuildEnvelopesXml( )
	{
		$cataloguePath = $this->getEnvelopesCataloguePath();

		$stmt = $this->db->prepare("
			SELECT * FROM env_catalogue
				WHERE cid=:cid AND sid=:sid
		");

		// organize the envelopes in categories
		$categories = array();
		if ($stmt->execute(array(":cid" => $this->companyId, ":sid" => $this->stateId))) {
			while ($envelope=$stmt->fetchObject()) {
				if (!isset($categories[$envelope->category])) {
					$categories[$envelope->category] = array();
				}
				$categories[$envelope->category][] = $envelope;
			}
		}

		// @TODO @UPGRADE: don't use ob_ functions here
		// create the envelope catalogue for this company/state
		ob_start();
		echo "<catalogue cid='{$this->companyId}' sid='{$this->stateId}'>\n";

		foreach ( $categories as $category=>$content ) {
			echo "\t<category name='{$category}'>\n";

			foreach ( $content as $envelope ) {
				echo "\t\t<envelope id='{$envelope->id}' name='{$envelope->name}'>\n";
				echo "\t\t\t<![CDATA[{$envelope->envelope}]]>\n";
				echo "\t\t</envelope>\n";
			}

			echo "\t</category>\n";
		}

		echo "</catalogue>";
		$catalogueData = ob_get_contents();
		ob_end_clean();

		// write a compressed / base64 encoded version of the output
		$zipContent		= base64_encode(gzcompress($catalogueData));

		$fw = fopen($cataloguePath, "wb");
		fputs($fw, $zipContent, strlen($zipContent));
		fclose($fw);
	}

	/**
	 */
	private function outputEnvelopesXml()
	{
		$cataloguePath = $this->getEnvelopesCataloguePath();

		// re-create the catalogue if it's missing
		if ( !file_exists( $cataloguePath ) ) {
			$this->rebuildEnvelopesXml( );
		}

		// if the file still doesn't exist, log an error
		if ( !file_exists( $cataloguePath ) ) {
			error_log( "LcApi.php::outputEnvelopesXml($this->companyId, $this->stateId): unable to create missing XML data file" );
		} 	else {
			// no cache directives are needed because the XML calls with a different timestamp every time
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: inline; filename=data.xml');
			header('Content-Length: ' . filesize( $cataloguePath ));
			readfile( $cataloguePath );
			exit;
		}
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Company Data: Houses

	/**
	 * @param $multihouse
	 * @param $exclusive
	 * @return string
	 */
	private function getFloorplansCataloguePath($multihouse, $exclusive )
	{
		return $this->s3Path(
			"response_cache/company_{$this->companyId}_{$this->stateId}_{$multihouse}".($exclusive?"_{$exclusive}":"").".zip"
		);
	}
	private function getThemeXmlPath( $themeId )
	{
		return $this->s3Path(
			"response_cache/theme_{$themeId}"
		);
	}

	/**
	 * @param $cid
	 * @param $sid
	 * @param $multihouse
	 * @param $exclusive
	 */
	private function rebuildCompanyXml($multihouse, $exclusive)
	{
        $cataloguePath = $this->getFloorplansCataloguePath( $multihouse, $exclusive );

	    if ($this->isHenleyFormat()) {
            $catalogueData = $this->getHenleyCatalogueData();

            // @DEBUG: for Henley, write a non-compressed version
            $fw = fopen($cataloguePath.".xml", "wb");
            fputs($fw, $catalogueData, strlen($catalogueData));
            fclose($fw);
        }   else {
	        $catalogueData = $this->getCompanyCatalogueData($multihouse, $exclusive);
        }

		// write a compressed & base64 encoded version of the output
		$zipContent		= base64_encode( gzcompress($catalogueData) );

		$fw = fopen($cataloguePath, "wb");
		fputs($fw, $zipContent, strlen($zipContent));
		fclose($fw);
	}

    /**
     * @param $multihouse
     * @param $exclusive
     * @param bool $includeHouseContents
     * @return string
     */
	private function getCompanyCatalogueData($multihouse, $exclusive, $includeHouseContents=true)
    {
        ob_start();
        // header('Content-Type: application/xml; charset=utf-8');
        echo "<company id=\"{$this->companyId}\">\n";

        // select all house ranges for this company, from the current state
        $stmtRanges	= $this->db->prepare("
			SELECT * FROM house_ranges
				WHERE cid=:cid AND state_id=:sid AND multihouse<=:multihouse AND exclusive<=:exclusive
				ORDER BY name
		");
        $stmtHouses = $this->db->prepare("
			SELECT * FROM house_svgs
				WHERE range_id=:range_id
				ORDER BY name
		");

        if ( $stmtRanges->execute(array(
            ":cid"			=> $this->companyId,
            ":sid"			=> $this->stateId,
            ":multihouse"	=> $multihouse,
            ":exclusive"	=> $exclusive
        )) ) {
            while ( $range=$stmtRanges->fetchObject() ) {
                $rangeStarted = false;

                // select all houses in this range
                if ( $stmtHouses->execute(array(":range_id"=>$range->id))) {
                    while ( $house=$stmtHouses->fetchObject() ) {
                        $hid		= $house -> id;

                        if ($includeHouseContents) {
                            // floorplans/[COMPANY_KEY]/[STATE.ABBREV]/[RANGE.FOLDER]/[HOUSE_NAME].svg
                            $houseUrl = $this->s3Path(
                                "floorplans/" .
                                $this->companyData->ckey . "/" .
                                strtolower($this->stateData->abbrev) . "/" .
                                strtolower($range->folder) . "/" .
                                $house->url
                            );

                            $houseData = file_get_contents($houseUrl);

                            // remove unused parameters from the house data to reduce load time
                            $houseData = str_replace(
                                "stroke=\"#000000\" stroke-width=\"0.7086614\" stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-miterlimit=\"10\"",
                                "",
                                $houseData
                            );
                        }

                        $houseName = htmlspecialchars($house->name);

                        if (!$rangeStarted) {
                            echo "<range id=\"{$range->id}\" name=\"{$range->name}\">";
                            $rangeStarted = true;
                        }

                        // read the house data
                        // echo "\t<house id=\"$hid\" name=\"{$house->name}\" url=\"{$house->url}\">\n";
                        echo "<house id=\"$hid\" name=\"{$houseName}\">\n";

                        if ($includeHouseContents) {
                            // AREA
                            echo "<area><![CDATA[{$house->area_data}]]></area>\n";
                            // SVG
                            echo "<svg><![CDATA[{$houseData}]]></svg>\n";
                        }

                        echo "</house>\n";
                    }

                    if ($rangeStarted) {
                        echo "</range>";
                    }
                }
            }
        }

        echo "</company>";
        $catalogueData = ob_get_contents();
        ob_end_clean();

        return $catalogueData;
    }

	/**
	 * @param $includeMultihouse
	 * @param $includeExclusive
	 */
	private function outputCompanyXml($includeMultihouse, $includeExclusive)
	{
		$xmlPath = $this->getFloorplansCataloguePath( $includeMultihouse, $includeExclusive );
		if ( !file_exists($xmlPath) ) {
			$this->rebuildCompanyXml( $includeMultihouse, $includeExclusive );
		}
		
		// if the file still doesn't exist, log an error
		if ( !file_exists($xmlPath) ) {
			error_log( "LcApi.php::outputCompanyXml($this->companyId, $this->stateId, $includeMultihouse, $includeExclusive): unable to create missing XML data file" );
		}
		else {
			// no cache directives are needed because the XML calls with a different timestamp every time
			header('Content-Type: application/octet-stream');
			header('Content-Disposition: inline; filename=data.xml');
			header('Content-Length: ' . filesize( $xmlPath ));
			readfile( $xmlPath );
			exit;
		}
	}

    /**
     * @param $includeMultihouse
     * @param $includeExclusive
     */
	private function outputCompanyRanges($includeMultihouse, $includeExclusive) {
        $this->outputXml(
            $this->getCompanyCatalogueData(
                $includeMultihouse,
                $includeExclusive,
                false
            )
        );
        exit;
    }

    /**
     * @param $cid int
     * @param $stateId int
     */
    public static function resetCompanyDataXml($cid, $stateId)
    {
        $worker = new LcApi(true);
        // prepare the data manually
        $worker->companyId = intval($cid);
        $worker->stateId   = intval($stateId);
        $worker->setupS3Client();
        // unlink everything
        foreach ([[0,0], [0,1], [1,0], [1,1]] as $config) {
            unlink($worker->getFloorplansCataloguePath($config[0], $config[1]));
        }
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Company Data: Henley custom XML format

    private function isHenleyFormat()
    {
        include_once __DIR__."/../../portal-henley/classes/PmUserInfo.php";
        return PmUserInfo::isCompanyAllowed($this->companyId);
    }

    private function getHenleyCatalogueData()
    {
        require_once(__DIR__."/../../portal-henley/classes/PmPlan.php");

        // select all house ranges for this company, from the current state
        $stmtRanges	= $this->db->prepare("
			SELECT * FROM house_ranges
				WHERE cid=:cid AND state_id=:sid AND multihouse<=:multihouse AND exclusive<=:exclusive
				ORDER BY name
		");
        $stmtHouses = $this->db->prepare("
			SELECT * FROM house_svgs
				WHERE range_id=:range_id
				ORDER BY name
		");

        $metadata = "";
        $houseList = array();

        if ( $stmtRanges->execute(array(
            ":cid"			=> $this->companyId,
            ":sid"			=> $this->stateId,
            ":multihouse"	=> 1,
            ":exclusive"	=> 1
        )) ) {
            while ( $range=$stmtRanges->fetchObject() ) {
                // select all houses in this range
                if ( $stmtHouses->execute(array(":range_id"=>$range->id))) {
                    while ( $house=$stmtHouses->fetchObject() ) {
                        $hid		= $house -> id;

                        // floorplans/[COMPANY_KEY]/[STATE.ABBREV]/[RANGE.FOLDER]/[HOUSE_NAME].svg
                        $houseUrl	= $this->s3Path(
                            "floorplans/" .
                            $this->companyData->ckey . "/" .
                            strtolower($this->stateData->abbrev) . "/" .
                            strtolower($range->folder) . "/" .
                            $house -> url
                        );
                        $houseData	= file_get_contents( $houseUrl );

                        // check if this is the metadata file
                        if ($house->name==PmPlan::META_FILE_NAME) {
                            $metadata = $houseData;

                            // try to cleanup Henley's messy TXT format
                            $start = strpos($metadata, "BEGIN_OPTION_DESCRIPTION");
                            if ($start !== false) {
                                $end = strpos($metadata, "\n", $start);

                                $metadata =
                                    substr($metadata, 0, $start) .
                                    "BEGIN_OPTION_DESCRIPTION\n" .
                                    substr($metadata, $end+1);
                            }
                        }   else {
                            // remove the <company> tags from the XML - they're not needed!
                            $houseData = preg_replace('/<[\/]?company.*>/', '', $houseData);
                            // remove the <?xml ... > tag
                            $houseData = preg_replace('/<\?xml.*\?>/' , '', $houseData);
                            // $houseList []= "<house id='{$house->id}' url='{$houseUrl}'>{$houseData}</house>";
                            $houseList []= "<house id='{$house->id}'>{$houseData}</house>";
                        }
                    }
                }
            }
        }

        $houseContent = implode("\n", $houseList);

        return "<companyData format='XML'>
    <meta><![CDATA[{$metadata}]]></meta>
    $houseContent
</companyData>
";
    }
}

?>