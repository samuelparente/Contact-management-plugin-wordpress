<?php
/*
Plugin Name: Contact Management Plugin
Description: A WordPress plugin to manage contacts.
Author: Samuel Parente
*/

// Initialize the plugin
add_action('init', 'contact_management_plugin_init');

function contact_management_plugin_init() {
    // Enqueue admin scripts and styles
    add_action('admin_enqueue_scripts', 'contact_management_plugin_enqueue_admin_scripts');

    // Create database tables on plugin activation
    register_activation_hook(__FILE__, 'contact_management_plugin_activate');

    // Add admin menu pages
    add_action('admin_menu', 'contact_management_plugin_admin_menu');

    // Add shortcode for public page
    add_shortcode('contact_management_people', 'contact_management_plugin_public_people');
}

//Scripts and styles
function contact_management_plugin_enqueue_admin_scripts() {
    wp_enqueue_style('contact-management-admin-style', plugins_url('assets/admin-style.css', __FILE__));
    wp_enqueue_script('contact-management-admin-script', plugins_url('assets/admin-script.js', __FILE__), array('jquery'), '1.0', true); //I created only the file...no code in it for now
}

// Create database tables on activation
function contact_management_plugin_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_people = $wpdb->prefix . 'contact_management_people';
    $table_contacts = $wpdb->prefix . 'contact_management_contacts';

    // Create the People table
    $sql_people = "CREATE TABLE $table_people (
        id INT NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        avatar_url VARCHAR(255),
        PRIMARY KEY (id),
        UNIQUE (email)
    ) $charset_collate;";

    // Create the Contacts table
    $sql_contacts = "CREATE TABLE $table_contacts (
        id INT NOT NULL AUTO_INCREMENT,
        person_id INT NOT NULL,
        country_code VARCHAR(10) NOT NULL,
        number VARCHAR(20) NOT NULL,
        PRIMARY KEY (id),
        FOREIGN KEY (person_id) REFERENCES $table_people(id),
        UNIQUE (country_code, number)
    ) $charset_collate;";

    //Create the tables...GO!
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_people);
    dbDelta($sql_contacts);
}

// Add admin menu pages
function contact_management_plugin_admin_menu() {
    add_menu_page(
        'Contact Management',
        'Contact Management',
        'manage_options',
        'contact-management',
        'contact_management_plugin_list_people'
    );

    add_submenu_page(
        'contact-management',
        'Add New Person',
        'Add New Person',
        'manage_options',
        'contact-management-add-person',
        'contact_management_plugin_add_edit_person'
    );

    add_submenu_page(
        'contact-management',
        'Edit Person',
        'Edit Person',
        'manage_options',
        'contact-management-edit-person',
        'contact_management_plugin_add_edit_person'
    );

    add_submenu_page(
        'contact-management',
        'Add/Edit Contact',
        'Add/Edit Contact',
        'manage_options',
        'contact-management-add-edit-contact',
        'contact_management_plugin_add_edit_contact'
    );
}

//Get prefixes via API for the country codes
function getCountryCodesFromAPI() {
    $api_url = 'https://restcountries.com/v3.1/all';
    
    // Initialize an empty array to store country codes
    $countryCodes = array(); 

    // Fetch data from the API
    $response = wp_remote_get($api_url);

    // Check for errors
    if (is_wp_error($response)) {
        return array('error' => 'Error fetching data from API.');
    }

    // Get the body of the response
    $body = wp_remote_retrieve_body($response);

    // Parse JSON
    $data = json_decode($body, true);

    // Check if data is valid JSON
    if (is_null($data)) {
        return array('error' => 'Invalid JSON data.');
    }

    // Loop through each country
    foreach ($data as $country) {
        // Check if the country has 'idd' key and 'root' and 'suffixes' keys inside it
        if (isset($country['idd']['root']) && isset($country['idd']['suffixes']) && is_array($country['idd']['suffixes']) && count($country['idd']['suffixes']) > 0) {
           
            $countryCode = $country['idd']['root'] . $country['idd']['suffixes'][0];

            $countryNameCode = "{$country['name']['common']} ({$countryCode})";

            $countryCodes[] = $countryNameCode;
        }
    }

    // Sort the country codes alphabetically
    sort($countryCodes);

    // Return the array of formatted country names and codes
    return $countryCodes;
}

// List People
function contact_management_plugin_list_people() {
    global $wpdb;
    $table_people = $wpdb->prefix . 'contact_management_people';

    $people = $wpdb->get_results("SELECT * FROM $table_people");

    echo "<h2>List of People</h2>";
    echo "<table class='widefat'>";
    echo "<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Actions</th></tr></thead>";
    echo "<tbody>";
    foreach ($people as $person) {
        echo "<tr>";
        echo "<td>{$person->id}</td>";
        echo "<td>{$person->name}</td>";
        echo "<td>{$person->email}</td>";
        echo "<td><a href='admin.php?page=contact-management-add-edit-contact&person_id={$person->id}'>Add Contact</a> | ";
        echo "<a href='admin.php?page=contact-management-edit-person&person_id={$person->id}'>Edit</a> | ";
        echo "<a href='admin.php?page=contact-management&action=delete_person&person_id={$person->id}'>Delete</a></td>";
        echo "</tr>";
    }
    echo "</tbody>";
    echo "</table>";
}

// Add/Edit Person..needs to add the functionality to edit...only add person...
function contact_management_plugin_add_edit_person() {
    global $wpdb;
    $table_people = $wpdb->prefix . 'contact_management_people';

    if (isset($_POST['submit'])) {
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $avatar_url = ''; // Have to write the code to fetch from the API...later on

        $wpdb->insert(
            $table_people,
            array(
                'name' => $name,
                'email' => $email,
                'avatar_url' => $avatar_url,
            )
        );
        echo "<div class='updated'><p>Person added successfully!</p></div>";
    }

    echo "<h2>Add New Person</h2>";
    echo "<form method='post' action=''>";
    echo "<label for='name'>Name:</label>";
    echo "<input type='text' name='name' id='name' required><br><br>";
    echo "<label for='email'>Email:</label>";
    echo "<input type='email' name='email' id='email' required><br><br>";
    echo "<input type='submit' name='submit' value='Add Person' class='button button-primary'>";
    echo "</form>";
}

//Add or edit contact - needs change to also edit the contacts...it is only showing Add contact...
function contact_management_plugin_add_edit_contact() {
    global $wpdb;
    $table_people = $wpdb->prefix . 'contact_management_people';
    $table_contacts = $wpdb->prefix . 'contact_management_contacts';

    // Get all country codes from the API function
    $allCountryCodes = getCountryCodesFromAPI();

    // Sort the country codes
    sort($allCountryCodes);

    if (isset($_POST['submit'])) {
        $person_id = intval($_POST['person_id']);
        $country_code = sanitize_text_field($_POST['country_code']);
        $number = sanitize_text_field($_POST['number']);

        $wpdb->insert(
            $table_contacts,
            array(
                'person_id' => $person_id,
                'country_code' => $country_code,
                'number' => $number,
            )
        );
        echo "<div class='updated'><p>Contact added successfully!</p></div>";
    }

    $person_id = isset($_GET['person_id']) ? intval($_GET['person_id']) : 0;
    $person = $wpdb->get_row("SELECT * FROM $table_people WHERE id = $person_id");

    if (!$person) {
        echo "<div class='error'><p>Person not found!</p></div>";
        return;
    }

    echo "<h2>Add/Edit Contact for {$person->name}</h2>";
    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='person_id' value='{$person->id}'>";
    echo "<label for='country_code'>Country Code:</label>";
    echo "<select name='country_code' id='country_code' required>";
    foreach ($allCountryCodes as $code) {
        echo "<option value='{$code}'>{$code}</option>";
    }
    echo "</select><br><br>";
    echo "<label for='number'>Number:</label>";
    echo "<input type='text' name='number' id='number' required><br><br>";
    echo "<input type='submit' name='submit' value='Add Contact' class='button button-primary'>";
    echo "</form>";
}


// Public page... missing filters
function contact_management_plugin_public_people($atts) {
    global $wpdb;
    $table_people = $wpdb->prefix . 'contact_management_people';

    $output = "<h2>List of People</h2>";
    $output .= "<ul>";
    $people = $wpdb->get_results("SELECT * FROM $table_people");
    foreach ($people as $person) {
        $output .= "<li>{$person->name} - {$person->email}</li>";
    }
    $output .= "</ul>";

    // debug text
    $output .= "<p>Shortcode Executed Successfully</p>";

    return $output;
}


// Initialize the plugin
contact_management_plugin_init();
