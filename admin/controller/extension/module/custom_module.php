<?php
/*
Version: 1.0
Author: Artur SuÅkowski
Website: http://artursulkowski.pl
*/

class ControllerExtensionModuleCustomModule extends Controller {
    private $error = array();

    public function index() {
        $this->language->load('extension/module/custom_module');

        $this->document->setTitle('Custom Module');

        $this->load->model('setting/setting');

        // Dodawanie plikÃ³w css i js do <head>
        $this->document->addStyle('view/stylesheet/custom_module.css');

        // Zapisywanie moduÅu
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('custom_module', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/module/custom_module', 'user_token=' . $this->session->data['user_token'], true));
        }

        // WyÅwietlanie powiadomieÅ
        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->session->data['success'])) {
            $data['success'] = $this->session->data['success'];
            unset($this->session->data['success']);
        } else {
            $data['success'] = '';
        }

        $data['action'] = $this->url->link('extension/module/custom_module', 'user_token=' . $this->session->data['user_token'], true);

        $data['user_token'] = $this->session->data['user_token'];

        // Åadowanie listy moduÅÃ³w
        $data['modules'] = array();

        if (isset($this->request->post['custom_module_module'])) {
            $data['modules'] = $this->request->post['custom_module_module'];
        } elseif ($this->config->get('custom_module_module')) {
            $data['modules'] = $this->config->get('custom_module_module');
        }

        // Layouts
        $this->load->model('design/layout');
        $data['layouts'] = $this->model_design_layout->getLayouts();

        // Languages
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Modules',
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Custom module',
            'href' => $this->url->link('extension/module/custom_module', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/custom_module', $data));
    }

    protected function validate() {
        // if (!$this->user->hasPermission('modify', 'extension/module/custom_module')) {
        //     $this->error['warning'] = $this->language->get('error_permission');
        // }

        if (!$this->error) {
            return true;
        } else {
            return false;
        }
    }
}
?>