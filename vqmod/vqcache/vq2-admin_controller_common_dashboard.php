<?php
class ControllerCommonDashboard extends Controller {
    public function index() {
        $this->load->language('common/dashboard');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['user_token'] = $this->session->data['user_token'];

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        // Check install directory exists
        if (is_dir(DIR_APPLICATION . 'install')) {
            $data['error_install'] = $this->language->get('error_install');
        } else {
            $data['error_install'] = '';
        }

        // Dashboard Extensions
        $dashboards = array();

        $this->load->model('setting/extension');

        // Get a list of installed modules
        $extensions = array();
        if ($this->user->hasPermission('access', 'common/dashboard')) {
            $extensions = $this->model_setting_extension->getInstalled('dashboard');
        }

        // Add all the modules which have multiple settings for each module
        foreach ($extensions as $code) {
            if ($this->config->get('dashboard_' . $code . '_status') && $this->user->hasPermission('access', 'extension/dashboard/' . $code)) {
                $output = $this->load->controller('extension/dashboard/' . $code . '/dashboard');

                if ($output) {
                    $dashboards[] = array(
                        'code'       => $code,
                        'width'      => $this->config->get('dashboard_' . $code . '_width'),
                        'sort_order' => $this->config->get('dashboard_' . $code . '_sort_order'),
                        'output'     => $output
                    );
                }
            }
        }

        $sort_order = array();

        foreach ($dashboards as $key => $value) {
            $sort_order[$key] = $value['sort_order'];
        }

        array_multisort($sort_order, SORT_ASC, $dashboards);

        // Split the array so the columns width is not more than 12 on each row.
        $width = 0;
        $column = array();
        $data['rows'] = array();

        foreach ($dashboards as $dashboard) {
            $column[] = $dashboard;

            $width = ($width + $dashboard['width']);

            if ($width >= 12) {
                $data['rows'][] = $column;

                $width = 0;
                $column = array();
            }
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

				$data['users_online_mini'] = $this->load->controller('b5b_qore_engine/dash_users_online_mini');
				$data['total_orders_mini'] = $this->load->controller('b5b_qore_engine/dash_total_orders_mini');
				$data['total_sales_mini'] = $this->load->controller('b5b_qore_engine/dash_total_sales_mini');
				$data['total_customers_mini'] = $this->load->controller('b5b_qore_engine/dash_total_customers_mini');
				$data['completed_orders_mini'] = $this->load->controller('b5b_qore_engine/dash_completed_orders_mini');
				$data['processing_orders_mini'] = $this->load->controller('b5b_qore_engine/dash_processing_orders_mini');
				$data['returned_orders_mini'] = $this->load->controller('b5b_qore_engine/dash_returned_orders_mini');
				$data['product_views_mini'] = $this->load->controller('b5b_qore_engine/dash_product_views_mini');

				$data['chart_most_viewed_products_medium'] = $this->load->controller('b5b_qore_engine/dash_chart_most_viewed_products_medium');

				$data['chart_sales_analytics_large'] = $this->load->controller('b5b_qore_engine/dash_chart_sales_analytics_large');
				$data['latest_orders_large'] = $this->load->controller('b5b_qore_engine/dash_latest_orders_large');
				$data['map_medium'] = $this->load->controller('b5b_qore_engine/dash_map_medium');
				$data['activity_medium'] = $this->load->controller('b5b_qore_engine/dash_activity_medium');
				$data['chart_top_customer_medium'] = $this->load->controller('b5b_qore_engine/dash_chart_top_customer_medium');
				$data['chart_top_product_medium'] = $this->load->controller('b5b_qore_engine/dash_chart_top_product_medium');
				

        // Run currency update
        if ($this->config->get('config_currency_auto')) {
            $this->load->model('localisation/currency');

            $this->model_localisation_currency->refresh();
        }

        foreach ($data['rows'] as $row => $content) {
            foreach ($content as $key => $value) {
                if ($value['code'] == 'store_sales') {
                    $data['store_sales'] = $value['output'];
                }
            }
        }


        if (!$this->user->hasPermission('access', 'common/dashboard')) {
            $data['rows'] = array();
            $data['users_online_mini'] = "";
            $data['total_orders_mini'] = "";
            $data['total_sales_mini'] = "";
            $data['total_customers_mini'] = "";
            $data['completed_orders_mini'] = "";
            $data['processing_orders_mini'] = "";
            $data['returned_orders_mini'] = "";
            $data['product_views_mini'] = "";
            $data['chart_most_viewed_products_medium'] = "";
            $data['chart_sales_analytics_large'] = "";
            $data['latest_orders_large'] = "";
            $data['map_medium'] = "";
            $data['activity_medium'] = "";
            $data['chart_top_customer_medium'] = "";
            $data['chart_top_product_medium'] = "";
            $data["store_sales"] = "";
        }


				/* B5B - QoreEngine - Start */
				$this->load->language('b5b_qore_engine/general/general');

				$data['b5b_qore_engine']['language']['error_incompatible_version'] = $this->language->get('error_incompatible_version');
				$data['b5b_qore_engine']['language']['text_base5builder'] = $this->language->get('text_base5builder');
				$data['b5b_qore_engine']['language']['text_base5builder_support'] = $this->language->get('text_base5builder_support');
				$data['b5b_qore_engine']['language']['error_error_occured'] = $this->language->get('error_error_occured');
				$data['b5b_qore_engine']['language']['text_refreshing_page'] = $this->language->get('text_refreshing_page');
				$data['b5b_qore_engine']['language']['text_powered_by'] = $this->language->get('text_powered_by');

				$this->load->model('b5b_qore_engine/general/settings');


				/* B5B - BETA FEATURE - START */
				// Check if page has been added to compatibility list

				$data['custom_page_is_compatible'] = FALSE;

				if(isset($this->request->get['route'])){;
					/*
					// Temporarily disabled. Will be enabled once feature is completed
					$custom_compatible_pages = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('compatible_page_route_circloid'));

					if($custom_compatible_pages && in_array($this->request->get['route'], $custom_compatible_pages)){
						$data['custom_page_is_compatible'] = TRUE;
					}
					*/
					$custom_compatible_pages = "";
				}

				/* B5B - BETA FEATURE - END */
				


				/* B5B - BETA FEATURE - START */
				// Check if page has been added to compatibility list

				$data['custom_page_is_compatible'] = FALSE;

				if(isset($this->request->get['route'])){;
					/*
					// Temporarily disabled. Will be enabled once feature is completed
					$custom_compatible_pages = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('compatible_page_route_circloid'));

					if($custom_compatible_pages && in_array($this->request->get['route'], $custom_compatible_pages)){
						$data['custom_page_is_compatible'] = TRUE;
					}
					*/
					$custom_compatible_pages = "";
				}

				/* B5B - BETA FEATURE - END */
				

				$table_exists = $this->model_b5b_qore_engine_general_settings->tableExsits('b5b_qore_engine_settings');

				if($table_exists){
					if(isset($this->request->get['route'])){
						$data['b5b_qore_engine_route'] = $this->request->get['route'];
					}else{
						$data['b5b_qore_engine_route'] = '';
					}

					$data['b5b_qore_engine_is_admin'] = 1;
					$data['b5b_qore_engine_active_theme'] = $this->model_b5b_qore_engine_general_settings->getSettings('active_theme');

					$info_path = DIR_TEMPLATE . 'b5b_qore_engine/themes/' . $data['b5b_qore_engine_active_theme'] . '/info.xml';

					if(file_exists($info_path)){
						$xml = simplexml_load_file($info_path);
						$data['b5b_qore_engine_active_theme_version'] = (string)$xml->version;
					}else{
						$data['b5b_qore_engine_active_theme_version'] = "";
					}

					$data['b5b_qore_engine_color_preset'] = $this->model_b5b_qore_engine_general_settings->getSettings('color_preset_' . $data['b5b_qore_engine_active_theme']);

					$data['b5b_qore_engine_white_label'] = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('white_label_' . $data['b5b_qore_engine_active_theme'] . '_settings'));
				}

				/* B5B - QoreEngine - End */
				

				/* B5B - QoreEngine - Start */
				$this->load->language('b5b_qore_engine/general/general');

				$data['b5b_qore_engine']['language']['error_incompatible_version'] = $this->language->get('error_incompatible_version');
				$data['b5b_qore_engine']['language']['text_base5builder'] = $this->language->get('text_base5builder');
				$data['b5b_qore_engine']['language']['text_base5builder_support'] = $this->language->get('text_base5builder_support');
				$data['b5b_qore_engine']['language']['error_error_occured'] = $this->language->get('error_error_occured');
				$data['b5b_qore_engine']['language']['text_refreshing_page'] = $this->language->get('text_refreshing_page');
				$data['b5b_qore_engine']['language']['text_powered_by'] = $this->language->get('text_powered_by');

				$this->load->model('b5b_qore_engine/general/settings');


				/* B5B - BETA FEATURE - START */
				// Check if page has been added to compatibility list

				$data['custom_page_is_compatible'] = FALSE;

				if(isset($this->request->get['route'])){;
					/*
					// Temporarily disabled. Will be enabled once feature is completed
					$custom_compatible_pages = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('compatible_page_route_circloid'));

					if($custom_compatible_pages && in_array($this->request->get['route'], $custom_compatible_pages)){
						$data['custom_page_is_compatible'] = TRUE;
					}
					*/
					$custom_compatible_pages = "";
				}

				/* B5B - BETA FEATURE - END */
				


				/* B5B - BETA FEATURE - START */
				// Check if page has been added to compatibility list

				$data['custom_page_is_compatible'] = FALSE;

				if(isset($this->request->get['route'])){;
					/*
					// Temporarily disabled. Will be enabled once feature is completed
					$custom_compatible_pages = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('compatible_page_route_circloid'));

					if($custom_compatible_pages && in_array($this->request->get['route'], $custom_compatible_pages)){
						$data['custom_page_is_compatible'] = TRUE;
					}
					*/
					$custom_compatible_pages = "";
				}

				/* B5B - BETA FEATURE - END */
				

				$table_exists = $this->model_b5b_qore_engine_general_settings->tableExsits('b5b_qore_engine_settings');

				if($table_exists){
					if(isset($this->request->get['route'])){
						$data['b5b_qore_engine_route'] = $this->request->get['route'];
					}else{
						$data['b5b_qore_engine_route'] = '';
					}

					$data['b5b_qore_engine_is_admin'] = 1;
					$data['b5b_qore_engine_active_theme'] = $this->model_b5b_qore_engine_general_settings->getSettings('active_theme');

					$info_path = DIR_TEMPLATE . 'b5b_qore_engine/themes/' . $data['b5b_qore_engine_active_theme'] . '/info.xml';

					if(file_exists($info_path)){
						$xml = simplexml_load_file($info_path);
						$data['b5b_qore_engine_active_theme_version'] = (string)$xml->version;
					}else{
						$data['b5b_qore_engine_active_theme_version'] = "";
					}

					$data['b5b_qore_engine_color_preset'] = $this->model_b5b_qore_engine_general_settings->getSettings('color_preset_' . $data['b5b_qore_engine_active_theme']);

					$data['b5b_qore_engine_white_label'] = unserialize($this->model_b5b_qore_engine_general_settings->getSettings('white_label_' . $data['b5b_qore_engine_active_theme'] . '_settings'));
				}

				/* B5B - QoreEngine - End */
				
        $this->response->setOutput($this->load->view('common/dashboard', $data));
    }
}