<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Install extends CI_Controller {

    public $tables = array();

    public function __construct()
    {
        parent::__construct();

        // Load the install model.
        $this->load->model('install_m', 'install');

        // Load some helpers.
        $this->load->helper(array('form', 'cookie', 'url'));

        // Load some libraries.
        $this->load->library(array('form_validation', 'session', 'messageci'));

        // Load the default config.
        $this->config->load('forums');

        // Add the tables.
        $this->tables = $this->config->item('tables');
    }

    public function index()
    {
        // Set some validation rules.
        $this->form_validation->set_rules('db_hostname', 'Hostname', 'trim|required');
        $this->form_validation->set_rules('db_username', 'Username', 'trim|required');
        $this->form_validation->set_rules('db_password', 'Password', 'trim|required');
        $this->form_validation->set_rules('db_name', 'Database Name', 'trim|required');

        if ($this->form_validation->run() === FALSE)
        {
            $this->load->view('install/header');
            $this->load->view('install/install');
            $this->load->view('install/footer');
        }
        else
        {
            $hostname = $this->input->post('db_hostname');
            $username = $this->input->post('db_username');
            $password = $this->input->post('db_password');
            $database = $this->input->post('db_name');

            if ($this->install->test_database($hostname, $username, $password, $database) === TRUE)
            {
                // Replace the hostname in the database.php config file.
                $find = "'hostname' =>";
                $replace = "\t" . "'hostname' => '".$hostname."'," . "\n";

                if ($this->install->edit_database_config($find, $replace) !== true)
                {
                    // Create a message.
                    $this->messageci->set( 'The hostname on your database config file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install'));
                }

                // Replace the username in the database.php config file.
                $find = "'username' =>";
                $replace = "\t" . "'username' => '".$username."'," . "\n";

                if ($this->install->edit_database_config($find, $replace) !== true)
                {
                    // Create a message.
                    $this->messageci->set( 'The username on your database config file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install'));
                }

                // Replace the password in the database.php config file.
                $find = "'password' =>";
                $replace = "\t" . "'password' => '".$password."'," . "\n";

                if ($this->install->edit_database_config($find, $replace) !== true)
                {
                    // Create a message.
                    $this->messageci->set( 'The password on your database config file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install'));
                }

                // Replace the database in the database.php config file.
                $find = "'database' =>";
                $replace = "\t" . "'database' => '".$database."'," . "\n";

                if ($this->install->edit_database_config($find, $replace) !== true)
                {
                    // Create a message.
                    $this->messageci->set( 'The database on your database config file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install'));
                }

                // Everything ok, proceed to the next step.
                redirect( site_url('install/create_tables') );
            }
            else
            {
                // Create a message.
                $this->messageci->set('There was a problem connecting to the database, please try again.');

                // Redirect.
                redirect( site_url('install/') );
            }
        }
    }

    public function create_tables()
    {
        // Set some validation rules.
        $this->form_validation->set_rules('first_iteration', 'First Iteration', 'trim|required|alpha_dash|max_length[64]');

        if ($this->form_validation->run() === FALSE)
        {
            $this->load->view('install/header');
            $this->load->view('install/tables');
            $this->load->view('install/footer');
        }
        else
        {
            if ($this->install->create_tables() === TRUE)
            {
                // Everything ok, proceed to the next step.
                redirect( site_url('install/site_settings') );
            }
            else
            {
                // Create a message.
                $this->messageci->set('There was a problem generating the database tables, please try again.');

                // Redirect.
                redirect( site_url('install/create_tables') );
            }
        }
    }

    public function site_settings()
    {

        // Delete the cookie created earlier.
        if(isset($_COOKIE['db_name']))
        {
            setcookie('db_name', '', time() - 3600);
        }

        // Load the database.
        $this->load->database();

        // Load the forums model.
        $this->load->model('forums_m', 'forums');

        // Get the language packs installed.
        $language = $this->forums->get_languages();

        if(!empty($language))
        {
            $language_options[NULL] = 'Select Language...';

            foreach($language as $row)
            {
                $language_options[$row->code] = $row->language;
            }
        }

        // Create the dropdown.
        $data['site_language'] = form_dropdown('site_language', $language_options, NULL, 'class="form-control"');

        // Set some validation rules.
        $this->form_validation->set_rules('base_url', 'Base Url', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('site_title', 'Site Title', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('encryption_key', 'Encryption Key', 'required|min_length[32]');
        $this->form_validation->set_rules('admin_username', 'Admin Username', 'trim|required|alpha_dash|min_length[4]|max_length[20]');
        $this->form_validation->set_rules('admin_password', 'Admin Password', 'trim|required|min_length[6]');
        $this->form_validation->set_rules('admin_password_confirm', 'Confirm Password', 'trim|required|min_length[6]|matches[admin_password]');
        $this->form_validation->set_rules('admin_email', 'Admin Email', 'trim|required|valid_email');
        $this->form_validation->set_rules('first_name', 'First Name', 'trim|required');
        $this->form_validation->set_rules('last_name', 'Last Name', 'trim|required');
        $this->form_validation->set_rules('site_language', 'Site Language', 'required');

        if ($this->form_validation->run() === FALSE)
        {
            $this->load->view('install/header');
            $this->load->view('install/settings', $data);
            $this->load->view('install/footer');

        }
        else
        {
            // Load Ion Auth.
            $this->load->library('ion_auth');

            // Load the settings library.
            $this->load->library('settings');

            // Gather the data.
            $base_url = $this->input->post('base_url');
            $site_title = addslashes($this->input->post('site_title'));
            $encryption_key = $this->input->post('encryption_key');
            $username = $this->input->post('admin_username');
            $password = $this->input->post('admin_password');
            $email = $this->input->post('admin_email');
            $group = array('2');
            $additional_data = array(
                'first_name' => $this->input->post('first_name'),
                'last_name' => $this->input->post('last_name'),
            );

            // Create the admin user.
            if ($this->ion_auth->register($username, $password, $email, $additional_data, $group) === FALSE )
            {
                // Create a message.
                $this->messageci->set('There was a problem creating the admin user, please try again!.');

                // Redirect
                redirect( site_url('install/site_settings') );
            }
            else
            {
                // Update the settings in the database.
                $this->settings->add_setting('site_name', addslashes($this->input->post('site_title')), 'site', 'yes');
                $this->settings->add_setting('site_email', $this->input->post('admin_email'), 'site', 'yes');
                $this->settings->add_setting('theme', 'default', 'site', 'yes');
                $this->settings->add_setting('admin_theme', 'default', 'site', 'yes');
                $this->settings->add_setting('gravatar_rating', 'x', 'gravatar', 'yes');
                $this->settings->add_setting('gravatar_default_image', 'mm', 'gravatar', 'yes');
                $this->settings->add_setting('gravatar_size', 50, 'gravatar', 'yes');
                $this->settings->add_setting('default_timezone', 'Europe/London', 'timezone', 'yes');
                $this->settings->add_setting('discussions_per_page', 10, 'discussions', 'yes');
                $this->settings->add_setting('comments_per_page', 10, 'comments', 'yes');
                $this->settings->add_setting('site_keywords', 'key, words, here', 'site', 'yes');
                $this->settings->add_setting('site_description', 'Enter a site description here.', 'site', 'yes');
                $this->settings->add_setting('site_language', $this->input->post('site_language'), 'site', 'yes');

                // Change the session driver.
                $find = '$config[\'sess_driver\'] =';
                $replace = '$config[\'sess_driver\'] = \'' . 'database' . '\';' . "\n";

                if ($this->install->edit_main_config($find, $replace) !== TRUE)
                {
                    // Create a message.
                    $this->messageci->set('The session driver in your main config.php file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install/site_settings') );
                }

                // Change session path.
                $find = '$config[\'sess_save_path\'] = ';
                $replace = '$config[\'sess_save_path\'] = \'' . 'ci_sessions' . '\';' . "\n";

                if ($this->install->edit_main_config($find, $replace) !== TRUE)
                {
                    // Create a message.
                    $this->messageci->set('The session path in your main config.php file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install/site_settings') );
                }

                // Replace base_url.
                $find = '$config[\'base_url\'] =';
                $replace = '$config[\'base_url\'] = \'' . $base_url . '\';' . "\n";

                if ($this->install->edit_main_config($find, $replace) !== TRUE)
                {
                    // Create a message.
                    $this->messageci->set('The base url in the your main config.php file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install/site_settings') );
                }

                // Replace the encryption key.
                $find = '$config[\'encryption_key\'] =';
                $replace = '$config[\'encryption_key\'] = \'' . $encryption_key . '\';' . "\n";

                if ($this->install->edit_main_config($find, $replace) !== TRUE)
                {
                    // Create a message.
                    $this->messageci->set('The encryption key in the your main config.php file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install/site_settings') );
                }

                // Replace the default route.
                $find = '$route[\'default_controller\'] =';
                $replace = '$route[\'default_controller\'] = \'' . 'forums' . '\';' . "\n";

                if ($this->install->edit_routes_config($find, $replace) !== TRUE)
                {
                    // Create a message.
                    $this->messageci->set('The default route in your routes.php file cannot be replaced.', 'error');

                    // Redirect.
                    redirect( site_url('install/site_settings') );
                }

                // Everything ok, go to the final step.
                redirect( site_url('install/complete') );
            }
        }

    }

    public function complete()
    {
        $this->load->view('install/header');
        $this->load->view('install/finish');
        $this->load->view('install/footer');
    }

    public function delete_files()
    {
        if ($this->install->delete_files())
        {
            redirect( site_url() );
        }
        else
        {
            echo 'Unable to delete installation files, please do it manually.';
        }
    }

}
