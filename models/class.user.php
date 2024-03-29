<?php
/*

UserFrosting Version: 0.1
By Alex Weissman
Copyright (c) 2014

Based on the UserCake user management system, v2.0.2.
Copyright (c) 2009-2012

UserFrosting, like UserCake, is 100% free and open-source.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the 'Software'), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

class loggedInUser {

	public $username = NULL;
	public $email = NULL;
	public $displayname = NULL;
	public $title = NULL;
	public $hash_pw = NULL;
	public $user_id = NULL;
	public $csrf_token = NULL;
    public $remember_me = NULL;
    public $remember_me_sessid = NULL;
    public $ll = NULL;
    public $ckey = NULL;
    public $domain = NULL;

    /** Landconnect: User Fields */
	public $state_id = NULL;
	public $company_id = NULL;
	public $has_multihouse = false;
	public $has_portal_access = false;
	public $has_exclusive = false;
	public $is_envelope_admin = false;
	public $is_discovery_manager = false;
	public $has_nearmap = false;
	public $billing_access_level = 0;

    /** Landconnect: Company Fields */
    public $theme_id = NULL;
    public $builder_id = NULL;
    public $chas_footprints = true;
    public $chas_discovery = false;
    public $chas_estates_access = false;

	// Simple function to update the last sign in of a user
	public function updateLastSignIn() {
		updateUserLastSignIn($this->user_id);
	}

	// Return the timestamp when this user's account was registered
	public function signupTimeStamp() {
		return fetchUserField($this->user_id, 'sign_up_stamp');
	}
	
	//csrf tokens
	public function csrf_token($regen = false)
    {
        if($regen === true) {
			//*make sure token is set, if so unset*//
			if(isset($_SESSION["__csrf_token"])) {
				unset($_SESSION["__csrf_token"]);
			}
			if (function_exists('openssl_random_pseudo_bytes')) {
				$rand_num = openssl_random_pseudo_bytes(16);//pull 16 bytes from /dev/random
			}else{
				/*
					RYO(Roll Your Own) random number gen.
					only used in the event openssl isn't available
				*/
				$rand = array();
				for($i = 0; $i < 64; $i++) {
					$random = mt_rand(rand(0,65012), mt_getrandmax());//get a random number between rand(0,65012) and mt rand max
					$rand[$i] = mt_rand($i, $random); //add an array key of $i and a value of a number between $i and the first random number
				}
				$rand = array_sum($rand); //shuffle the random number, then sum the values
				$rand_num = str_shuffle($rand * 64); //multiply the rand number by 64 and shuffle the string.
			}
			if(isset($rand_num)) {
				$build_string = $rand_num . $this->username . time();
				if(isset($build_string)) {
					$_SESSION["__csrf_token"] = hash('whirlpool', str_shuffle($build_string));
					$this->csrf_token = $_SESSION["__csrf_token"];
					return $this->csrf_token;
				}
			}
        }else{
			//the user already has a token
            return $this->csrf_token;
        }
    }
	
    //validate token
    public function csrf_validate($token)
    {
        if($token !== $this->csrf_token)
        {
            $this->csrf_token(false); //do not regenerate token, as user may have multiple instances of the site open, with different forms.
            return false;//let the view handle the error.
        }else{
            return true;//cookin with gas
        }
    }

    //Logout
    public function userLogOut()
    {
        global $oauth_client_id;

        if ($oauth_client_id) {
            $this->logoutFronSso();
        }

        destroySession("userCakeUser");
    }

    public function portalMultiFileUpload()
    {
        // Burbank || Landconnect
        if ($this->company_id==3 || $this->company_id==1) {
            return true;
        }

        return false;
    }

    /**
     * Logout from all platforms
     * @return $this
     */
    protected function logoutFronSso()
    {
        global $oauth_provider_url;

        $access_token = fetchUserField($this->user_id, 'access_token');

        if ($access_token) {
            $curl = curl_init($oauth_provider_url . '/api/logout');
            $authorization = "Authorization: Bearer $access_token";
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, []);
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
            $data = curl_exec($curl);
            curl_close($curl);
            $response = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                addAlert("warning", "Can't logout from sso");
            } elseif (isset($response['error'])) {
                addAlert("warning", $response['hint']);
            }
        }

        return $this;
    }
}

?>