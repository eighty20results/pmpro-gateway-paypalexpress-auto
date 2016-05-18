<?php
/*
Plugin Name: E20R PayPal Express Gateway (automatic confirmation)
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-customizations/
Description: PayPal Express payment gateway for PMPro w/Automatic confirmation
Version: .1
Author: Thomas Sjolshagen @ Stranger Studios <thomas@eighty20results.com>
Author URI: http://www.strangerstudios.com
*/

//include pmprogateway
if (file_exists(plugin_dir_path(__FILE__) . "../paid-memberships-pro/classes/gateways/class.pmprogateway.php")) {
    require_once(plugin_dir_path(__FILE__) . "../paid-memberships-pro/classes/gateways/class.pmprogateway.php");
} else {
    if (WP_DEBUG) {
        error_log("PMPro isn't activated???");
    }
    // exit quietly since PMPro isn't active & present.
    return;
}

//load class when WP is loaded
add_action('wp_loaded', array('PMProGateway_paypalexpress_auto', 'init'));

class PMProGateway_paypalexpress_auto extends PMProGateway
{

    /** @var string $gateway_url - Link to the payment gateway */
    private $gateway_url;

    /** @var string $ipn_notifylink - Link to the IPN handler on this server */
    private $ipn_notifylink;

    /** @var string $API_UserName - The PayPal Express username (email) to use */
    private $API_UserName;

    /** @var  string $API_Password - Password used to access PayPal services */
    private $API_Password;

    /** @var  string $API_Signature - The public signature used to access PayPal services */
    private $API_Signature;

    /** @var  string $gateway_version - PayPal API version to use */
    private $gateway_version;

    /** @var - PMProGateway_paypalexpress $_this - The class instance */
    private static $_this;

    /** @var int $requestNo - track the PayPal request # (for the PAYMENTREQUEST_n_* variables) */
    private $requestNo = 0;

    /** @var  array $httpParsedResponseAr - Array of parsed response values from PayPal Express checkout process */
    private $httpParsedResponseAr;

    public function __construct($gateway = null)
    {

        $trace = debug_backtrace();
        $caller = $trace[1];
        $who_called_me = '';

        if (isset($caller['class']))
            $who_called_me .= "{$caller['class']}::";

        $who_called_me .= "{$caller['function']}() -";

        if (WP_DEBUG) {
            error_log("Instantiating the PMProGateway_PayPalExpress_Auto class: {$who_called_me}");
        }

        $this->gateway = $gateway;

        return $this->gateway;
    }

    /**
     * A access this class using the singleton pattern
     *
     * @return PMProGateway_paypalexpress - PayPal Express payment gateway class
     *
     * @since 1.8.10
     */
    public static function get_instance()
    {
        if (null === self::$_this) {
            self::$_this = new self;
        }

        return self::$_this;
    }

    /**
     * Run on WP init
     *
     * @since 1.8
     */
    static function init()
    {

        $gw = PMProGateway_paypalexpress_auto::get_instance();

        //make sure PayPal Express is a gateway option
        add_filter('pmpro_gateways', array($gw, 'pmpro_gateways'));

        //add fields to payment settings
        add_filter('pmpro_payment_options', array($gw, 'pmpro_payment_options'));

        /*
            Filter pmpro_next_payment to get actual value
            via the PayPal API. This is disabled by default
            for performance reasons, but you can enable it
            by copying this line into a custom plugin or
            your active theme's functions.php and uncommenting
            it there.
        */
        //add_filter('pmpro_next_payment', array('PMProGateway_paypalexpress_auto', 'pmpro_next_payment'), 10, 3);

        /**
         * This code is the same for PayPal Website Payments Pro, PayPal Express, and PayPal Standard
         * So we only load it if we haven't already.
         *
         * @since PayPal Express (Auto) v1.0 add-on: Always loading these gateway options
         */
        add_filter('pmpro_payment_option_fields', array(
            $gw,
            'pmpro_payment_option_fields'
        ), 10, 2);

        //code to add at checkout
        $gateway = pmpro_getGateway();

        if ($gateway == "paypalexpress_auto") {

            add_filter('pmpro_valid_gateways', array($gw, 'update_gateways'), 10, 1);

            add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_include_payment_information_fields', '__return_false');

            add_filter('pmpro_required_billing_fields', array($gw, 'pmpro_required_billing_fields'), 10, 1);

            add_filter('pmpro_checkout_new_user_array', array(
                $gw,
                'pmpro_checkout_new_user_array'
            ));
            add_filter('pmpro_checkout_confirmed', array(
                $gw,
                'pmpro_checkout_confirmed'
            ));
            add_action('pmpro_checkout_before_processing', array(
                $gw,
                'pmpro_checkout_before_processing'
            ));
            add_filter('pmpro_checkout_default_submit_button', array(
                $gw,
                'pmpro_checkout_default_submit_button'
            ));
            add_action('pmpro_checkout_after_form', array(
                $gw,
                'pmpro_checkout_after_form'
            ));

            add_action('wp_enqueue_scripts', array($gw, 'enqueue'));
        }

        $gw->set_gateway();
        $gw->set_ipn_link();
    }

    /**
     * Make sure this gateway is in the gateways list
     *
     * @since 1.8
     * @since PayPal Express (Auto) v1.0 add-on: PayPal Express (Auto-confirm) gateway
     */
    static function pmpro_gateways($gateways)
    {

        if (!isset($gateways['paypalexpress_auto'])) {
            $gateways['paypalexpress_auto'] = __('PayPal Express (Auto-confirm)', 'pmpro');
        }

        return $gateways;
    }

    public function enqueue()
    {

        wp_enqueue_script('pmpro_gateway_paypalexpress_auto', plugin_dir_url(__FILE__) . 'js/pmpro_paypalexpress_auto.js', array('jquery', 'ssmemberships_js', 'pmpro_discountcodes'), PMPRO_VERSION);
    }

    /**
     * @param $gateways
     * @return array
     *
     * @since PayPal Express (Auto) v1.0 add-on: paypalexpress_auto is a valid gateway
     */
    static function update_gateways($gateways)
    {

        $gateways[] = 'paypalexpress_auto';

        if (WP_DEBUG) {
            error_log("Loading the PayPal Express (Auto-confirm) gateway as valid");
        }

        return $gateways;
    }

    /**
     * Get a list of payment options that the this gateway needs/supports.
     *
     * @since 1.8
     */
    static function getGatewayOptions()
    {
        $options = array(
            'sslseal',
            'nuclear_HTTPS',
            'gateway_environment',
            'ppauto_gateway_email',
            'ppauto_apiusername',
            'ppauto_apipassword',
            'ppauto_apisignature',
            'paypal_skip_confirmation',
            'currency',
            'use_ssl',
            'tax_state',
            'tax_rate',
            'confirm_page_id',
            'cancel_page_id',
        );

        return $options;
    }

    /**
     * Configure the URL to use when connecting to the payment gateway.
     *
     * @access private
     * @since v1.8.10
     */
    public function set_gateway()
    {
        global $gateway_environment;
        $environment = $gateway_environment;

        $this->gateway_version = urlencode('109.0');

        $this->API_UserName = urlencode(pmpro_getOption("apiusername"));
        $this->API_Password = urlencode(pmpro_getOption("apipassword"));
        $this->API_Signature = urlencode(pmpro_getOption("apisignature"));

        $this->gateway_url = "https://api-3t.paypal.com/nvp";

        if ("sandbox" === $environment || "beta-sandbox" === $environment) {
            $this->gateway_url = "https://api-3t.$environment.paypal.com/nvp";
        }

    }

    /**
     * Configure the IPN NOTIFYURL content using standard WP query variable/link functionality
     *
     * @access private
     * @since v1.8.10
     */
    public function set_ipn_link()
    {

        $ipn_service_link = admin_url('admin-ajax.php');
        $ipn_args = array('action' => 'ipnhandler');

        $this->ipn_notifylink = add_query_arg($ipn_args, $ipn_service_link);
    }

    /**
     * Set payment options for payment settings page.
     *
     * @since 1.8
     * @since PayPal Express (Auto) v1.0 add-on: Avoiding duplicate option keys
     */
    static function pmpro_payment_options($options)
    {

        //get paypal settings
        $gw = PMProGateway_paypalexpress_auto::get_instance();
        $paypal_options = $gw->getGatewayOptions();

        //merge with others.
        $options = array_unique(array_merge($paypal_options, $options));

        if (WP_DEBUG) {
            error_log("Returning " . count($options) . " options to calling function");
        }

        return $options;
    }

    /**
     * Display fields for this gateway's options.
     *
     * @since 1.8
     */
    static function pmpro_payment_option_fields($values, $gateway)
    {

        global $pmpro_pages;

        ?>
        <tr class="pmpro_settings_divider gateway paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <td colspan="2">
                <?php _e('PayPal Settings', 'pmpro'); ?>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <td colspan="2">
                <strong><?php _e('Note', 'pmpro'); ?>
                    :</strong> <?php _e('We do not recommend using PayPal Standard. We suggest using PayPal Express, Website Payments Pro (Legacy), or PayPal Pro (Payflow Pro). <a target="_blank" href="http://www.paidmembershipspro.com/2013/09/read-using-paypal-standard-paid-memberships-pro/">More information on why can be found here.</a>', 'pmpro'); ?>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="ppauto_gateway_email"><?php _e('Gateway Account Email', 'pmpro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="ppauto_gateway_email" name="ppauto_gateway_email" size="60"
                       value="<?php echo esc_attr($values['ppauto_gateway_email']) ?>"/>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="ppauto_apiusername"><?php _e('API Username', 'pmpro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="ppauto_apiusername" name="ppauto_apiusername" size="60"
                       value="<?php echo esc_attr($values['ppauto_apiusername']) ?>"/>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="ppauto_apipassword"><?php _e('API Password', 'pmpro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="ppauto_apipassword" name="ppauto_apipassword" size="60"
                       value="<?php echo esc_attr($values['ppauto_apipassword']) ?>"/>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="ppauto_apisignature"><?php _e('API Signature', 'pmpro'); ?>:</label>
            </th>
            <td>
                <input type="text" id="ppauto_apisignature" name="ppauto_apisignature" size="60"
                       value="<?php echo esc_attr($values['ppauto_apisignature']) ?>"/>
            </td>
        </tr>
        <!--
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="confirm_page_id"><?php _e('Payment complete page', 'pmpro'); ?>:</label>
            </th>
            <td>
                <?php wp_dropdown_pages(
                    array(
                        'selected' => $values['confirm_page_id'],
                        'echo' => 1,
                        'name' => 'confirm_page_id',
                        'id' => 'confirm_page_id',
                        'show_option_none' => 'Checkout page',
                        'option_none_value' => $pmpro_pages['checkout'],
                    )
                ) ?>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="cancel_page_id"><?php _e('Payment Cancelled page', 'pmpro'); ?>:</label>
            </th>
            <td>
                <?php wp_dropdown_pages(
                    array(
                        'selected' => $values['cancel_page_id'],
                        'echo' => 1,
                        'name' => 'cancel_page_id',
                        'id' => 'cancel_page_id',
                        'show_option_none' => 'N/A',
                        'option_none_value' => -1,
                    )
                ) ?>
            </td>
        </tr>
        -->
        <tr class="gateway gateway_paypal gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="paypal_skip_confirmation"><?php _e('Require confirmation', 'pmpro'); ?>:</label>
            </th>
            <td>
                <select id="paypal_skip_confirmation" name="paypal_skip_confirmation">
                    <option
                        value="0" <?php selected(pmpro_getOption('paypal_skip_confirmation'), 0); ?>><?php _e("Yes", "pmpro"); ?></option>
                    <option
                        value="1" <?php selected(pmpro_getOption('paypal_skip_confirmation'), 1); ?>><?php _e("No", "pmpro"); ?></option>
                </select>
                <span><small><? _e("Return the user to this site to confirm payment (Note: Payment will not be collected until they click the 'Confirm' button).", "pmpro"); ?></small></span>
            </td>
        </tr>
        <tr class="gateway gateway_paypalexpress_auto"
            <?php if ($gateway != "paypalexpress_auto" && $gateway != "paypal" && $gateway != "paypalexpress") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label><?php _e('IPN Handler URL', 'pmpro'); ?>:</label>
            </th>
            <td>
                <p><?php _e('To fully integrate with PayPal, be sure to set your IPN Handler URL to ', 'pmpro'); ?>
				<pre><?php

                    $gw = PMProGateway_paypalexpress_auto::get_instance();
                    $gw->set_ipn_link();

                    echo $gw->ipn_notifylink;
                    ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Remove required billing fields
     *
     * @since 1.8
     */
    static function pmpro_required_billing_fields($fields)
    {

        unset($fields['bfirstname']);
        unset($fields['blastname']);
        unset($fields['baddress1']);
        unset($fields['bcity']);
        unset($fields['bstate']);
        unset($fields['bzipcode']);
        unset($fields['bphone']);
        unset($fields['bemail']);
        unset($fields['bcountry']);
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);

        return $fields;
    }

    /**
     * Save session vars before processing
     *
     * @since 1.8
     */
    static function pmpro_checkout_before_processing()
    {
        global $current_user, $gateway;

        //save user fields for PayPal Express
        if (!$current_user->ID) {

            //get values from post
            $username = isset($_REQUEST['username']) ? sanitize_user($_REQUEST['username']) : "";
            $password = isset($_REQUEST['password']) ? $_REQUEST['password'] : null;
            $bemail = isset($_REQUEST['bemail']) ? sanitize_email($_REQUEST['bemail']) : null;

            //save to session
            $_SESSION['pmpro_signup_username'] = $username;
            $_SESSION['pmpro_signup_password'] = $password;
            $_SESSION['pmpro_signup_email'] = $bemail;
        }

        //can use this hook to save some other variables to the session
        do_action("pmpro_paypalexpress_session_vars");
    }

    /**
     * Review and Confirmation code
     *
     * @since 1.8
     *
     * @since PayPal Express (Auto) v1.0 add-on - bypasses "review" page & saved data directly
     */
    static function pmpro_checkout_confirmed($pmpro_confirmed)
    {

        global $pmpro_msg, $pmpro_msgt, $pmpro_level, $current_user, $pmpro_review, $pmpro_paypal_token, $discount_code, $bemail;

        $token = isset($_REQUEST['token']) ? sanitize_text_field($_REQUEST['token']) : null;

        if (is_null($token) && isset($_REQUEST['review'])) {
            $pmpro_msg = __("The PayPal Token was not received.", "pmpro");
            $pmpro_msgt = "pmpro_error";
        }

        if (WP_DEBUG) {
            error_log("Processing after being returned from PayPal express site: " . print_r($_REQUEST, true));
        }

        //returned from PayPal Express site
        if (!is_null($token)) {

            $_SESSION['payer_id'] = isset($_REQUEST['PayerID']) ? sanitize_text_field($_REQUEST['PayerID']) : null;
            $_SESSION['paymentAmount'] = isset($_REQUEST['paymentAmount']) ? floatval($_REQUEST['paymentAmount']) : 0.00;
            $_SESSION['currCodeType'] = isset($_REQUEST['currencyCodeType']) ? sanitize_text_field($_REQUEST['currencyCodeType']) : null;
            $_SESSION['paymentType'] = isset($_REQUEST['paymentType']) ? sanitize_text_field($_REQUEST['paymentType']) : null;

            $morder = new MemberOrder();

            $morder->getMemberOrderByPayPalToken($token);
            $morder->Token = $morder->paypal_token;
            $pmpro_paypal_token = $morder->paypal_token;

            if ($morder->Token) {
                if ($morder->Gateway->getExpressCheckoutDetails($morder)) {
                    $pmpro_confirmed = $morder;
                    $pmpro_review = false; // make sure we skip the review page
                } else {
                    $pmpro_msg = $morder->error;
                    $pmpro_msgt = "pmpro_error";
                }
            } else {
                $pmpro_msg = __("The PayPal Token was lost.", "pmpro");
                $pmpro_msgt = "pmpro_error";
            }
        } elseif (!is_null($token) && empty($pmpro_msg) &&
            ((true == pmpro_getOption('paypal_skip_confirmation') && $pmpro_review) ||
                (!empty($_REQUEST['confirm'])))
        ) {

            if (WP_DEBUG) {
                error_log("Returning from PayPal & skipping the confirmation page");
            }

            $morder = new MemberOrder();
            $morder->getMemberOrderByPayPalToken($token);

            $morder->Token = $morder->paypal_token;
            $pmpro_paypal_token = $morder->paypal_token;

            if ($morder->Token) {
                //set up values
                $morder->membership_id = $pmpro_level->id;
                $morder->membership_name = $pmpro_level->name;
                $morder->discount_code = $discount_code;
                $morder->InitialPayment = $pmpro_level->initial_payment;
                $morder->PaymentAmount = $pmpro_level->billing_amount;
                $morder->ProfileStartDate = date("Y-m-d") . "T0:0:0";
                $morder->BillingPeriod = $pmpro_level->cycle_period;
                $morder->BillingFrequency = $pmpro_level->cycle_number;
                $morder->Email = $bemail;

                //set up level var
                $morder->getMembershipLevel();
                $morder->membership_level = apply_filters("pmpro_checkout_level", $morder->membership_level);

                //tax
                $morder->subtotal = $morder->InitialPayment;
                $morder->getTax();
                if ($pmpro_level->billing_limit) {
                    $morder->TotalBillingCycles = $pmpro_level->billing_limit;
                }

                if (pmpro_isLevelTrial($pmpro_level)) {
                    $morder->TrialBillingPeriod = $pmpro_level->cycle_period;
                    $morder->TrialBillingFrequency = $pmpro_level->cycle_number;
                    $morder->TrialBillingCycles = $pmpro_level->trial_limit;
                    $morder->TrialAmount = $pmpro_level->trial_amount;
                }

                if ($morder->confirm()) {
                    $pmpro_confirmed = true;
                } else {
                    $pmpro_msg = $morder->error;
                    $pmpro_msgt = "pmpro_error";
                }
            } else {
                $pmpro_msg = __("The PayPal Token was lost.", "pmpro");
                $pmpro_msgt = "pmpro_error";
            }
        }

        if (!empty($morder)) {
            return array("pmpro_confirmed" => $pmpro_confirmed, "morder" => $morder);
        } else {
            return $pmpro_confirmed;
        }
    }

    /**
     * Swap in user/pass/etc from session
     *
     * @since 1.8
     */
    static function pmpro_checkout_new_user_array($new_user_array)
    {
        global $current_user;

        if (!$current_user->ID) {
            //reload the user fields
            $new_user_array['user_login'] = $_SESSION['pmpro_signup_username'];
            $new_user_array['user_pass'] = $_SESSION['pmpro_signup_password'];
            $new_user_array['user_email'] = $_SESSION['pmpro_signup_email'];

            //unset the user fields in session
            unset($_SESSION['pmpro_signup_username']);
            unset($_SESSION['pmpro_signup_password']);
            unset($_SESSION['pmpro_signup_email']);
        }

        return $new_user_array;
    }

    //PayPal Express, this is run first to authorize from PayPal
    function setExpressCheckout(&$order)
    {

        global $pmpro_currency;
        global $pmpro_pages;

        $options = apply_filters("pmpro_payment_options", array('gateway'));

        if (empty($order->code)) {
            $order->code = $order->getRandomCode();
        }

        //clean up a couple values
        $order->payment_type = "PayPal Express";
        $order->CardType = "";
        $order->cardtype = "";

        //taxes on initial amount
        $initial_payment = $order->InitialPayment;
        $initial_payment_tax = $order->getTaxForPrice($initial_payment);
        $initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);

        //taxes on the amount
        $amount = $order->PaymentAmount;
        $amount_tax = $order->getTaxForPrice($amount);
        $amount = round((float)$amount + (float)$amount_tax, 2);

        //paypal profile stuff
        $nvpStr = "&PAYMENTREQUEST_{$this->requestNo}_AMT={$initial_payment}";
        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_CURRENCYCODE={$pmpro_currency}";

        if (!empty($order->ProfileStartDate) && strtotime($order->ProfileStartDate, current_time("timestamp")) > 0) {
            $nvpStr .= "&PROFILESTARTDATE={$order->ProfileStartDate}";
        }

        if (!empty($order->BillingFrequency)) {
            $nvpStr .= "&BILLINGPERIOD={$order->BillingPeriod}";
            $nvpStr .= "&BILLINGFREQUENCY={$order->BillingFrequency}";
            $nvpStr .= "&AUTOBILLAMT=AddToNextBilling";
            $nvpStr .= "&L_BILLINGTYPE{$this->requestNo}=RecurringPayments";
        }

        $descr = substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127);
        $descr = apply_filters('pmpro_paypal_level_description', $descr, $order->membership_level->name, $order, get_bloginfo("name"));
        $descr = urlencode($descr);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_DESC={$descr}";

        // Build the link for PayPal to use when sending IPN messages
        $this->set_ipn_link();
        $ipnLink = urlencode($this->ipn_notifylink);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_NOTIFYURL={$ipnLink}";
        $nvpStr .= "&NOSHIPPING=1";

        // configure the description to use for the billing agreement
        $a_text = substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127);
        $a_text = apply_filters('pmpro_paypal_billing_agreement_text', $a_text, $order, get_bloginfo("name"));
        $a_text = urlencode($a_text);

        $nvpStr .= "&L_BILLINGAGREEMENTDESCRIPTION{$this->requestNo}={$a_text}";
        $nvpStr .= "&L_PAYMENTTYPE0=Any";

        //if billing cycles are defined
        if (!empty($order->TotalBillingCycles)) {
            $nvpStr .= "&TOTALBILLINGCYCLES={$order->TotalBillingCycles}";
        }

        //if a trial period is defined
        if (!empty($order->TrialBillingPeriod)) {

            $trial_amount = $order->TrialAmount;
            $trial_tax = $order->getTaxForPrice($trial_amount);
            $trial_amount = round((float)$trial_amount + (float)$trial_tax, 2);

            $nvpStr .= "&TRIALBILLINGPERIOD={$order->TrialBillingPeriod}";
            $nvpStr .= "&TRIALBILLINGFREQUENCY={$order->TrialBillingFrequency}";
            $nvpStr .= "&TRIALAMT={$trial_amount}";
        }

        if (!empty($order->TrialBillingCycles)) {
            $nvpStr .= "&TRIALTOTALBILLINGCYCLES={$order->TrialBillingCycles}";
        }

        // set the correct return page/action after finishing on PayPal
        $confirmation_page = pmpro_getOption('confirm_page_id');

        if (-1 == $confirmation_page && $pmpro_pages['checkout'] != $confirmation_page) {

            if (WP_DEBUG) {
                error_log("Using custom page ({$confirmation_page}) as the confirmation page");
            }

            $return_url = get_permalink($confirmation_page);

        } else {

            if (WP_DEBUG) {
                error_log("Using default checkout page as the confirmation page");
            }

            $return_url = get_permalink($pmpro_pages['checkout']);
        }

        // required arguments for the return link
        $r_args = array(
            'review' => $order->code,
            'gateway' => 'paypalexpress_auto',
            'level' => $order->membership_id,
        );


        if (!empty($order->discount_code)) {

            $r_args["discount_code"] = $order->discount_code;
        }

        $r_args = apply_filters('pmpro_paypal_auto_return_url_parameters', $r_args, $order, $return_url);
        $return_link = urlencode(add_query_arg($r_args, $return_url));

        $nvpStr .= "&RETURNURL={$return_link}";

        $additional_parameters = apply_filters("pmpro_paypal_auto_other_parameters", array());

        if (!empty($additional_parameters)) {

            foreach ($additional_parameters as $key => $value) {
                $nvpStr .= "&" . urlencode($key) . "=" . urlencode($value);
            }
        }

        // add page to land on if order is cancelled on PayPal
        $cancel_page = pmpro_getOption('cancel_page_id');

        $cancel_args = array(

            'gateway' => 'paypalexpress_auto',
            'cancelling' => true,
        );

        if (-1 != $cancel_page && $pmpro_pages['levels'] != $cancel_page) {
            if (WP_DEBUG) {
                error_log("Using custom page ({$cancel_page}) as the cancellation page");
            }

            $cancel_url = get_permalink($cancel_page);

        } else {
            if (WP_DEBUG) {
                error_log("Using the levels page as the cancellation page");
            }

            $cancel_url = pmpro_url("levels");
        }

        $cancel_url = urlencode(add_query_arg($cancel_args, $cancel_url));

        $nvpStr .= "&CANCELURL={$cancel_url}";

        $account_optional = apply_filters('pmpro_paypal_account_optional', true);

        if ($account_optional) {

            $nvpStr .= "&SOLUTIONTYPE=Sole";
            $nvpStr .= "&LANDINGPAGE=Billing";
        }

        $nvpStr = apply_filters("pmpro_set_express_checkout_nvpstr", $nvpStr, $order);

        // Increment the request counter
        $this->requestNo++;

        $this->httpParsedResponseAr = $this->PPHttpPost('SetExpressCheckout', $nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            $order->status = "token";
            $order->paypal_token = urldecode($this->httpParsedResponseAr['TOKEN']);

            //update order
            $order->saveOrder();

            //redirect to paypal
            $pp_options = array(
                'cmd' => '_express-checkout'
            );

            if (!empty($this->httpParsedResponseAr["TOKEN"])) {
                $pp_options['token'] = $this->httpParsedResponseAr['TOKEN'];
            }

            if (pmpro_getOption('paypal_skip_confirmation')) {
                $pp_options['useraction'] = 'commit';
            }

            $paypal_url = "https://www.paypal.com/webscr?";

            $environment = pmpro_getOption("gateway_environment");

            if ("sandbox" === $environment || "beta-sandbox" === $environment) {

                $paypal_url = "https://www.sandbox.paypal.com/webscr?";
            }

            foreach ($pp_options as $param => $val) {

                $cmds[] = "{$param}={$val}";
            }

            $paypal_params = implode('&', $cmds);

            $paypal_url .= $paypal_params;

            if (WP_DEBUG) {
                error_log("Redirecting to PayPal: {$paypal_url}");
            }

            wp_redirect($paypal_url);
            exit;

            //exit('SetExpressCheckout Completed Successfully: '.print_r($this->httpParsedResponseAr, true));

        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']);
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            return false;
            //exit('SetExpressCheckout failed: ' . print_r($httpParsedResponseAr, true));
        }

        //write session?

        //redirect to PayPal
    }

    /**
     * Swap in our submit buttons.
     *
     * @param boolean $show - Not used in this gateway (PMPro PayPal Express (Auto) gateway).
     *
     * @return boolean - Always returns false
     * @since 1.8
     */
    static function pmpro_checkout_default_submit_button($show)
    {
        global $gateway, $pmpro_requirebilling;

        //show our submit buttons
        ?>
        <?php if ($gateway == "paypal" || $gateway == "paypalexpress" || $gateway == "paypalstandard" || $gateway == "paypalexpress_auto") { ?>
        <span id="pmpro_paypalexpress_auto_checkout"
              <?php if (($gateway != "paypalexpress_auto" && $gateway != "paypalexpress" && $gateway != "paypalstandard") || !$pmpro_requirebilling) { ?>style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1"/>
				<input type="image" value="<?php _e('Check Out with PayPal', 'pmpro'); ?> &raquo;"
                       src="<?php echo apply_filters("pmpro_paypal_button_image", "https://www.paypal.com/en_US/i/btn/btn_xpressCheckout.gif"); ?>"/>
			</span>
    <?php } ?>

        <span id="pmpro_submit_span"
              <?php if (($gateway == "paypalexpress_auto" || $gateway == "paypalexpress" || $gateway == "paypalstandard") && $pmpro_requirebilling) { ?>style="display: none;"<?php } ?>>
				<input type="hidden" name="submit-checkout" value="1"/>
				<input type="submit" class="pmpro_btn pmpro_btn-submit-checkout"
                       value="<?php if ($pmpro_requirebilling) {
                           _e('Submit and Check Out', 'pmpro');
                       } else {
                           _e('Submit and Confirm', 'pmpro');
                       } ?> &raquo;"/>
			</span>
        <?php

        //don't show the default
        return false;
    }

    /**
     * Scripts for checkout page.
     *
     * @since 1.8
     */
    static function pmpro_checkout_after_form()
    {
        ?>
        <script>
            <!--
            //choosing payment method
            jQuery('input[name=gateway]').click(function () {
                if (jQuery(this).val() == 'paypal') {
                    jQuery('#pmpro_paypalexpress_checkout').hide();
                    jQuery('#pmpro_billing_address_fields').show();
                    jQuery('#pmpro_payment_information_fields').show();
                    jQuery('#pmpro_submit_span').show();
                }
                else {
                    jQuery('#pmpro_billing_address_fields').hide();
                    jQuery('#pmpro_payment_information_fields').hide();
                    jQuery('#pmpro_submit_span').hide();
                    jQuery('#pmpro_paypalexpress_auto_checkout').show();
                }
            });

            //select the radio button if the label is clicked on
            jQuery('a.pmpro_radio').click(function () {
                jQuery(this).prev().click();
            });
            -->
        </script>
        <?php
    }

    function getExpressCheckoutDetails(&$order)
    {

        $nvpStr = "&TOKEN={$order->Token}";

        $nvpStr = apply_filters("pmpro_get_express_checkout_details_nvpstr", $nvpStr, $order);

        /* Make the API call and store the results in an array.  If the
        call was a success, show the authorization details, and provide
        an action to complete the payment.  If failed, show the error
        */
        $this->httpParsedResponseAr = $this->PPHttpPost('GetExpressCheckoutDetails', $nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            $order->status = "review";

            //update order
            $order->saveOrder();

            return true;
        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']);
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            return false;
            //exit('SetExpressCheckout failed: ' . print_r($httpParsedResponseAr, true));
        }
    }

    function charge(&$order)
    {

        if (WP_DEBUG) {
            error_log("Processing at charge for PayPal Express (gateway::charge())");
        }

        global $pmpro_currency;

        if (empty($order->code)) {
            $order->code = $order->getRandomCode();
        }

        //taxes on the amount
        $amount = $order->InitialPayment;
        $amount_tax = $order->getTaxForPrice($amount);
        $order->subtotal = $amount;
        $amount = round((float)$amount + (float)$amount_tax, 2);

        //paypal profile stuff
        $nvpStr = "";
        if (!empty($order->Token)) {
            $nvpStr .= "&TOKEN={$order->Token}";
        }
        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_AMT={$amount}";
        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_CURRENCYCODE={$pmpro_currency}";

        /*
        if(!empty($amount_tax))
            $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_TAXAMT={$amount_tax}";
        */

        if (!empty($order->BillingFrequency)) {
            $nvpStr .= "&BILLINGPERIOD={$order->BillingPeriod}";
            $nvpStr .= "&BILLINGFREQUENCY={$order->BillingFrequency}";
            $nvpStr .= "&AUTOBILLAMT=AddToNextBilling";
        }

        $descr = substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127);
        $descr = apply_filters('pmpro_paypal_level_description', $descr, $order->membership_level->name, $order, get_bloginfo("name"));
        $descr = urlencode($descr);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_DESC={$descr}";

        $this->set_ipn_link();
        $ipn_link = urlencode($this->ipn_notifylink);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_NOTIFYURL={$ipn_link}";
        $nvpStr .= "&NOSHIPPING=1";

        $nvpStr .= "&PAYERID={$_SESSION['payer_id']}";
        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_PAYMENTACTION=sale";

        $nvpStr = apply_filters("pmpro_do_paypal_auto_checkout_payment_nvpstr", $nvpStr, $order);

        $order->nvpStr = $nvpStr;

        $this->httpParsedResponseAr = $this->PPHttpPost('DoExpressCheckoutPayment', $nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            $order->payment_transaction_id = urldecode($this->httpParsedResponseAr["PAYMENTREQUEST_{$this->requestNo}_TRANSACTIONID"]);
            $order->status = "success";

            // increment request counter
            $this->requestNo++;

            //update order
            $order->saveOrder();

            return true;
        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']);
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            // increment request counter
            $this->requestNo++;

            return false;
            //exit('SetExpressCheckout failed: ' . print_r($httpParsedResponseAr, true));
        }
    }

    function subscribe(&$order)
    {
        global $pmpro_currency;

        if (empty($order->code)) {
            $order->code = $order->getRandomCode();
        }

        //filter order before subscription. use with care.
        $order = apply_filters("pmpro_subscribe_order", $order, $this);

        //taxes on initial amount
        $initial_payment = $order->InitialPayment;
        $initial_payment_tax = $order->getTaxForPrice($initial_payment);
        $initial_payment = round((float)$initial_payment + (float)$initial_payment_tax, 2);

        //taxes on the amount
        $amount = $order->PaymentAmount;
        $amount_tax = $order->getTaxForPrice($amount);
        //$amount = round((float)$amount + (float)$amount_tax, 2);

        //paypal profile stuff
        $nvpStr = "";

        if (!empty($order->Token)) {
            $nvpStr .= "&TOKEN={$order->Token}";
        }

        $nvpStr .= "&INITAMT={$initial_payment}";
        $nvpStr .= "&AMT={$amount}";
        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_CURRENCYCODE={$pmpro_currency}";
        $nvpStr .= "&PROFILESTARTDATE={$order->ProfileStartDate}";

        if (!empty($amount_tax)) {
            $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_TAXAMT={$amount_tax}";
        }

        $nvpStr .= "&BILLINGPERIOD={$order->BillingPeriod}";
        $nvpStr .= "&BILLINGFREQUENCY={$order->BillingFrequency}";
        $nvpStr .= "&AUTOBILLAMT=AddToNextBilling";

        $this->set_ipn_link();
        $ipn_link = urlencode($this->ipn_notifylink);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_NOTIFYURL={$ipn_link}";

        $descr = substr($order->membership_level->name . " at " . get_bloginfo("name"), 0, 127);
        $descr = apply_filters('pmpro_paypal_level_description', $descr, $order->membership_level->name, $order, get_bloginfo("name"));
        $descr = urlencode($descr);

        $nvpStr .= "&PAYMENTREQUEST_{$this->requestNo}_DESC={$descr}";

        //if billing cycles are defined
        if (!empty($order->TotalBillingCycles)) {

            $nvpStr .= "&TOTALBILLINGCYCLES={$order->TotalBillingCycles}";
        }

        //if a trial period is defined
        if (!empty($order->TrialBillingPeriod)) {

            $trial_amount = $order->TrialAmount;
            $trial_tax = $order->getTaxForPrice($trial_amount);
            $trial_amount = round((float)$trial_amount + (float)$trial_tax, 2);

            $nvpStr .= "&TRIALBILLINGPERIOD={$order->TrialBillingPeriod}";
            $nvpStr .= "&TRIALBILLINGFREQUENCY={$order->TrialBillingFrequency}";
            $nvpStr .= "&TRIALAMT={$trial_amount}";
        }

        if (!empty($order->TrialBillingCycles)) {
            $nvpStr .= "&TRIALTOTALBILLINGCYCLES={$order->TrialBillingCycles}";
        }

        $nvpStr = apply_filters("pmpro_create_recurring_payments_profile_nvpstr", $nvpStr, $order);

        $this->nvpStr = $nvpStr;

        ///echo str_replace("&", "&<br />", $nvpStr);
        ///exit;

        $this->httpParsedResponseAr = $this->PPHttpPost('CreateRecurringPaymentsProfile', $this->nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            $order->status = "success";
            $order->payment_transaction_id = urldecode($this->httpParsedResponseAr['PROFILEID']);
            $order->subscription_transaction_id = urldecode($this->httpParsedResponseAr['PROFILEID']);

            //update order
            $order->saveOrder();

            return true;
        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']);
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            return false;
        }
    }

    function cancel(&$order)
    {
        //paypal profile stuff
        $nvpStr = "";
        $prof_id = urlencode($order->subscription_transaction_id);
        $note = apply_filters('pmpro_paypal_auto_cancel_note', __("User requested cancellation.", "pmpro"), $order);
        $note = urlencode($note);

        $nvpStr .= "&PROFILEID={$prof_id}";
        $nvpStr .= "&ACTION=Cancel";
        $nvpStr .= "&NOTE={$note}";

        $nvpStr = apply_filters("pmpro_manage_recurring_payments_profile_status_nvpstr", $nvpStr, $order);

        $this->httpParsedResponseAr = $this->PPHttpPost('ManageRecurringPaymentsProfileStatus', $nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            $order->updateStatus("cancelled");

            return true;
        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']) . ". " . __("Please contact the site owner or cancel your subscription from within PayPal to make sure you are not charged going forward.", "pmpro");
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            return false;
        }
    }

    /**
     * Process charge or subscription after confirmation.
     *
     * @param MemberOrder $order - A valid & instantiated MemberOrder class/object
     *
     * @return boolean - Status of the charge or subscribe operation against the PayPal gateway
     *
     * @since 1.8
     */
    function confirm(&$order)
    {

        if (WP_DEBUG) {
            error_log("Processing confirmation for PayPal Express (gateway::confirm())");
        }

        if (pmpro_isLevelRecurring($order->membership_level)) {
            $order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time("timestamp"))) . "T0:0:0";
            $order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);

            return $this->subscribe($order);

        } else {

            return $this->charge($order);
        }
    }

    /**
     * Process at checkout
     *
     * Repurposed in v2.0. The old process() method is now confirm().
     */
    function process(&$order)
    {

        if (WP_DEBUG) {
            error_log("Processing at checkout for PayPal Express (gateway::process())");
        }
//		$order->payment_type     = "PayPal Express";
//		$order->cardtype         = "";
        $order->ProfileStartDate = date("Y-m-d", strtotime("+ " . $order->BillingFrequency . " " . $order->BillingPeriod)) . "T0:0:0";
        $order->ProfileStartDate = apply_filters("pmpro_profile_start_date", $order->ProfileStartDate, $order);

        return $this->setExpressCheckout($order);
    }

    function getSubscriptionStatus(&$order)
    {

        if (empty($order->subscription_transaction_id)) {
            return false;
        }

        //paypal profile stuff
        $nvpStr = "";

        $subscr_id = urlencode($order->subscription_transaction_id);
        $nvpStr .= "&PROFILEID={$subscr_id}";

        $nvpStr = apply_filters("pmpro_get_recurring_payments_profile_details_nvpstr", $nvpStr, $order);

        $this->httpParsedResponseAr = $this->PPHttpPost('GetRecurringPaymentsProfileDetails', $nvpStr);

        if ("SUCCESS" == strtoupper($this->httpParsedResponseAr["ACK"]) ||
            "SUCCESSWITHWARNING" == strtoupper($this->httpParsedResponseAr["ACK"])
        ) {

            return $this->httpParsedResponseAr;
        } else {
            $order->status = "error";
            $order->errorcode = $this->httpParsedResponseAr['L_ERRORCODE0'];
            $order->error = urldecode($this->httpParsedResponseAr['L_LONGMESSAGE0']);
            $order->shorterror = urldecode($this->httpParsedResponseAr['L_SHORTMESSAGE0']);

            return false;
        }
    }

    /**
     * Filter pmpro_next_payment to get date via API if possible
     *
     * @since 1.8.5
     */
    static function pmpro_next_payment($timestamp, $user_id, $order_status)
    {
        //find the last order for this user
        if (!empty($user_id)) {
            //get last order
            $order = new MemberOrder();
            $order->getLastMemberOrder($user_id, $order_status);

            //check if this is a paypal express order with a subscription transaction id
            if (!empty($order->id) && !empty($order->subscription_transaction_id) && $order->gateway == "paypalexpress") {
                //get the subscription status
                $status = $order->getGatewaySubscriptionStatus();

                if (!empty($status) && !empty($status['NEXTBILLINGDATE'])) {
                    //found the next billing date at PayPal, going to use that
                    $timestamp = strtotime(urldecode($status['NEXTBILLINGDATE']), current_time('timestamp'));
                } elseif (!empty($status) && !empty($status['PROFILESTARTDATE']) && $order_status == "cancelled") {
                    //startdate is in the future and we cancelled so going to use that as the next payment date
                    $startdate_timestamp = strtotime(urldecode($status['PROFILESTARTDATE']), current_time('timestamp'));
                    if ($startdate_timestamp > current_time('timestamp')) {
                        $timestamp = $startdate_timestamp;
                    }
                }
            }
        }

        return $timestamp;
    }

    /**
     * PAYPAL Function
     * Send HTTP POST Request
     *
     * @param    string $methodName_ - The API method name
     * @param    string $nvpStr_ - The POST Message fields in &name=value pair format
     * @param     array $nvp_array - The POST message fields as an array (may be empty)
     *
     * @return    array    Parsed HTTP Response body
     */
    private function PPHttpPost($methodName_, $nvpStr_, $nvp_array = array())
    {
        $this->set_gateway();

        //NVPRequest for submitting to server
        $nvp_req = array(
            'METHOD' => urlencode($methodName_),
            'VERSION' => $this->gateway_version,
            'PWD' => $this->API_Password,
            'USER' => $this->API_UserName,
            'SIGNATURE' => $this->API_Signature,
            'BUTTONSOURCE' => urlencode(PAYPAL_BN_CODE)
        );

        $nvp_keyval = array();

        foreach ($nvp_req as $key => $val) {
            $nvp_keyval[] = urlencode($key) . "=" . urlencode($val);
        }

        $nvpreq = implode('&', $nvp_keyval);
        $nvpreq .= $nvpStr_;

        if (!empty($nvp_array) && empty($nvpStr_)) {

            $nvp_keyval = array();

            foreach ($nvp_array as $key => $val) {
                $nvp_keyval[] = "{$key}={$val}";
            }

            $nvpreq .= "&" . implode("&", $nvp_keyval);
        }

        if (WP_DEBUG) {
            error_log("URL for PayPal Express transaction: {$nvpreq}");
            error_log("Sending to: {$this->gateway_url}");
        }

        //post to PayPal
        $response = wp_remote_post($this->gateway_url, array(
                'timeout' => apply_filters('pmpro_gateways_connect_timeout', 60),
                'sslverify' => apply_filters('pmpro_paypal_auto_verify_ssl', FALSE),
                'httpversion' => apply_filters('pmpro_gateways_http_version', '1.1'),
                'body' => $nvpreq
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wp_die("{$methodName_} failed: $error_message");
        } else {
            //extract the response details
            $httpParsedResponseAr = array();
            parse_str(wp_remote_retrieve_body($response), $httpParsedResponseAr);

            //check for valid response
            if ((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
                exit("Invalid HTTP Response for POST request($nvpreq) to {$this->gateway_url}.");
            }
        }

        return $httpParsedResponseAr;
    }
}
