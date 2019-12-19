<?php
/* ****Customizations for my Paid Memberships Pro Setup**** */
 
	/* ***WP Bouncer*** */  
		define ('wp_bouncer_ignore_admins', true);
		define ('wp_bouncer_redirect_url', 'https://www.seductful.xxx');

	/* ***Display a Membership Expiration/Renewal Date PMPro account page*** */
		// Change the expiration text to show the next payment date instead of the expiration date
		// This hook is setup in the wp_renewal_dates_setup function below
		function my_pmpro_expiration_text($expiration_text) {
		    global $current_user;
		    $next_payment = pmpro_next_payment();
		    if($next_payment){
		        $expiration_text = date_i18n( get_option('date_format'), $next_payment);
		    }
		    return $expiration_text;
		}
		// Change "expiration date" to "renewal date"
		// This hook is setup in the wp_renewal_dates_setup function below
		function change_expiration_date_to_renewal_date($translated_text, $original_text, $domain) {
		    if($domain === 'paid-memberships-pro' && $original_text === 'Expiration')
		        $translated_text = 'Renewal Date';
		    return $translated_text;
		}
		// Logic to figure out if the user has a renewal date and to setup the hooks to show that instead
		function wp_renewal_dates_setup() {
		    global $current_user, $pmpro_pages;
		    // in case PMPro is not active
		    if(!function_exists('pmpro_getMembershipLevelForUser'))
		        return;
		    // If the user has an expiration date, tell PMPro it is expiring "soon" so the renewal link is shown
		    $membership_level = pmpro_getMembershipLevelForUser($current_user->ID);            
		    if(!empty($membership_level) && !pmpro_isLevelRecurring($membership_level))
		        add_filter('pmpro_is_level_expiring_soon', '__return_true');    
		    if(is_page($pmpro_pages[ 'account' ] ) ) {
		        // If the user has no expiration date, add filter to change "expiration date" to "renewal date"        
		        if(!empty($membership_level) && (empty($membership_level->enddate) || $membership_level->enddate == '0000-00-00 00:00:00'))
		            add_filter('gettext', 'change_expiration_date_to_renewal_date', 10, 3);        
		        // Check to see if the user's last order was with PayPal Express, else assume it was with Stripe.
		        // These filters make the next payment calculation more accurate by hitting the gateway
		        $order = new MemberOrder();
		        $order->getLastMemberOrder( $current_user->ID );
		        if( !empty($order) && $order->gateway == 'paypalexpress') {
		            add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress', 'pmpro_next_payment'), 10, 3);    
		        }else{
		            add_filter('pmpro_next_payment', array('PMProGateway_stripe', 'pmpro_next_payment'), 10, 3);    
		        }
		    }
		    add_filter('pmpro_account_membership_expiration_text', 'my_pmpro_expiration_text');    
		}
		add_action('wp', 'wp_renewal_dates_setup', 11);

	/* ***Add custom fields to membership checkout page using PMPro Register Helper Plugin*** */
		//we have to put everything in a function called on init, so we are sure Register Helper is loaded
		function my_pmprorh_init()
		{
			//don't break if Register Helper is not loaded
			if(!function_exists('pmprorh_add_registration_field')) {
				return false;
			}
			//define the fields
			$fields[] = new PMProRH_Field(
				'referral',         // input name, will also be used as meta key
				'text',				// type of field
				array(
					'label'		=> 'How/Where did you hear about Seductful.xxx?',	 // custom field label
					'profile'	=> 'admin',					// only show in profile for admins
					'memberslistcsv' => true,				//include in csv exports
				)
			);
			$fields[] = new PMProRH_Field(
				'referral',         // input name, will also be used as meta key
				'text',				// type of field
				array(
					'label'		=> 'Would like to ANNONAMOUSLY refer someone to Seductful.xxx? If YES, please supply us with their email address.',	 // custom field label
					'profile'	=> 'admin',					// only show in profile for admins
					'memberslistcsv' => true,				//include in csv exports
				)
			);
			//add the fields into a new checkout_boxes are of the checkout page
			foreach($fields as $field)
				pmprorh_add_registration_field(
					'checkout_boxes',				// location on checkout page
					$field							// PMProRH_Field object
				);
		}
		add_action('init', 'my_pmprorh_init');

	/* ***Prevent email addresses being used as username*** */
		function pmpro_registration_checks_no_email_user_login($continue) {
			//if there are earlier problems, don't bother checking
			if(!$continue)
				return;
			//make sure the username doesn't look like an email address (contain a @)
			global $username;
			if(!empty($username) && strpos($username, "@") !== false) {
				$continue = false;
				pmpro_setMessage( 'Your Username may not be an email address.', 'pmpro_error' );
			}
			return $continue;
		}
		add_filter('pmpro_registration_checks', 'pmpro_registration_checks_no_email_user_login');

	/* ***Changing 'Membership' to 'Subscription' for Paid Memberships Pro*** */
		// @param  string $output_text     this represents the end result
		// @param  string $input_text      what is written in the code that we want to change
		// @param  string $domain          text-domain of the plugin/theme that contains the code
		// @return string                  the result of the text transformation
		function my_gettext_membership( $output_text, $input_text, $domain ) {
			if (! is_admin() && 'paid-memberships-pro' === $domain) {
				$output_text = str_replace('Membership Level', 'Subscription', $output_text);
				$output_text = str_replace('membership level', 'subscription', $output_text);
				$output_text = str_replace('Membership', 'Subscription', $output_text);
				$output_text = str_replace('membership', 'subscription', $output_text);
			}
			return $output_text;
		}
		add_filter( 'gettext', 'my_gettext_membership', 10, 3 );