<?php
include(DIR_STORAGE . 'vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;


class ControllerToolBackup extends Controller {
    public function index() {
        $this->load->language('tool/backup');

        $this->document->setTitle($this->language->get('heading_title'));

        if (isset($this->session->data['error'])) {
            $data['error_warning'] = $this->session->data['error'];

            unset($this->session->data['error']);
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('tool/backup', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['user_token'] = $this->session->data['user_token'];

        $data['export'] = $this->url->link('tool/backup/export', 'user_token=' . $this->session->data['user_token'], true);

        $this->load->model('tool/backup');

        $data['tables'] = $this->model_tool_backup->getTables();

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tool/backup', $data));
    }

    public function import() {
        $this->load->language('tool/backup');

        $json = array();

        if (!$this->user->hasPermission('modify', 'tool/backup')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (isset($this->request->files['import']['tmp_name']) && is_uploaded_file($this->request->files['import']['tmp_name'])) {
            $filename = tempnam(DIR_UPLOAD, 'bac');

            move_uploaded_file($this->request->files['import']['tmp_name'], $filename);
        } elseif (isset($this->request->get['import'])) {
            $filename = html_entity_decode($this->request->get['import'], ENT_QUOTES, 'UTF-8');
        } else {
            $filename = '';
        }

        if (!is_file($filename)) {
            $json['error'] = $this->language->get('error_file');
        }

        if (isset($this->request->get['position'])) {
            $position = $this->request->get['position'];
        } else {
            $position = 0;
        }

        if (!$json) {
            // We set $i so we can batch execute the queries rather than do them all at once.
            $i = 0;
            $start = false;

            $handle = fopen($filename, 'r');

            fseek($handle, $position, SEEK_SET);

            while (!feof($handle) && ($i < 100)) {
                $position = ftell($handle);

                $line = fgets($handle, 1000000);

                if (substr($line, 0, 14) == 'TRUNCATE TABLE' || substr($line, 0, 11) == 'INSERT INTO') {
                    $sql = '';

                    $start = true;
                }

                if ($i > 0 && (substr($line, 0, 24) == 'TRUNCATE TABLE `oc_user`' || substr($line, 0, 30) == 'TRUNCATE TABLE `oc_user_group`')) {
                    fseek($handle, $position, SEEK_SET);

                    break;
                }

                if ($start) {
                    $sql .= $line;
                }

                if ($start && substr($line, -2) == ";\n") {
                    $this->db->query(substr($sql, 0, strlen($sql) -2));

                    $start = false;
                }

                $i++;
            }

            $position = ftell($handle);

            $size = filesize($filename);

            $json['total'] = round(($position / $size) * 100);

            if ($position && !feof($handle)) {
                $json['next'] = str_replace('&amp;', '&', $this->url->link('tool/backup/import', 'user_token=' . $this->session->data['user_token'] . '&import=' . $filename . '&position=' . $position, true));

                fclose($handle);
            } else {
                fclose($handle);

                unlink($filename);

                $json['success'] = $this->language->get('text_success');

                $this->cache->delete('*');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function export() {
        $this->load->language('tool/backup');

        if (!isset($this->request->post['backup'])) {
            $this->session->data['error'] = $this->language->get('error_export');

            $this->response->redirect($this->url->link('tool/backup', 'user_token=' . $this->session->data['user_token'], true));
        } elseif (!$this->user->hasPermission('modify', 'tool/backup')) {
            $this->session->data['error'] = $this->language->get('error_permission');

            $this->response->redirect($this->url->link('tool/backup', 'user_token=' . $this->session->data['user_token'], true));
        } else {
            $this->response->addheader('Pragma: public');
            $this->response->addheader('Expires: 0');
            $this->response->addheader('Content-Description: File Transfer');
            $this->response->addheader('Content-Type: application/octet-stream');
            $this->response->addheader('Content-Disposition: attachment; filename="' . DB_DATABASE . '_' . date('Y-m-d_H-i-s', time()) . '_backup.sql"');
            $this->response->addheader('Content-Transfer-Encoding: binary');

            $this->load->model('tool/backup');

            $this->response->setOutput($this->model_tool_backup->backup($this->request->post['backup']));
        }
    }

    public function import_product() {
        $this->load->language('tool/backup');
        $this->load->model('tool/backup');
        $this->load->model('catalog/product');
        $this->load->model('localisation/language');

        $json = array();

        if (!$this->user->hasPermission('modify', 'tool/backup')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (isset($this->request->files['import']['tmp_name']) && is_uploaded_file($this->request->files['import']['tmp_name'])) {
            $filename = tempnam(DIR_UPLOAD, 'excel');
            move_uploaded_file($this->request->files['import']['tmp_name'], $filename);
        } elseif (isset($this->request->get['import'])) {
            $filename = html_entity_decode($this->request->get['import'], ENT_QUOTES, 'UTF-8');
        } else {
            $filename = '';
        }

        if (isset($this->request->get['position'])) {
            $row = $this->request->get['position'];
        } else {
            $row = 1;
        }

        if (!$json) {
            $inputFileType = \PhpOffice\PhpSpreadsheet\IOFactory::identify($filename);
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader($inputFileType);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filename);
            $sheet_data = $spreadsheet->getActiveSheet()->toArray();
            $total = count($sheet_data);
            $i = 0;

            while($row <= $total && $i < 20) {
                if (!isset($sheet_data[$row])) {
                    break;
                }

                if (!$data = $this->setProductData($sheet_data[$row], $this->model_tool_backup)) {
                    $i++;
                    $row++;
                    continue;
                }

                $this->model_catalog_product->addProduct($data);
                $i++;
                $row++;
            }
        }

        $json['total'] = round(($total / $row) * 20);

        if ($row < $total) {
            $json['next'] = str_replace('&amp;', '&', $this->url->link('tool/backup/import_product', 'user_token=' . $this->session->data['user_token'] . '&import=' . $filename . '&position=' . $row, true));
        } else {
            unlink($filename);
            $json['success'] = $this->language->get('text_success');
            $this->cache->delete('*');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function setProductData($row, $model) {
        $dir_image = DIR_IMAGE . "catalog/toplu_urun/";
        $path_prefix = "catalog/toplu_urun/";
        if (empty($row[0])) {
            return false;
        }

        $language_id = 1;

        $name = $row[0];
        $description = $row[1];
        $package_description[2];
        $product_model = $row[3];
        $barcode = $row[4];
        $tax_rate = $row[5];
        $tax_included_price = $row[6];
        $tax_free_price = $row[7];
        $quantity = $row[8];
        $categories = $row[9];
        $manufacturer = $row[10];
        $stores = $row[11];
        $status = $row[12];
        $image = $row[13];

        if (is_file($dir_image . $image)) {
            $image_path = $path_prefix . $image;
        } else if (is_file($dir_image . $image . ".jpg")) {
            $image_path = $path_prefix . $image . ".jpg";
        } else if (is_file($dir_image . $image . ".png")) {
            $image_path = $path_prefix . $image . ".png";
        } else if (is_file($dir_image . $image . ".gif")) {
            $image_path = $path_prefix . $image . ".gif";
        } else if (is_file($dir_image . $image . ".jpeg")) {
            $image_path = $path_prefix . $image . ".jpeg";
        }

        return array(
            'model' => (string)$product_model,
            'sku' => (string)$product_model,
            'upc' => (string)$product_model,
            'ean' => (string)$product_model,
            'jan' => (string)$product_model,
            'isbn' => (string)$product_model,
            'mpn' => (string)$product_model,
            'image' => $image_path,
            'tag' => '',
            'location' => '',
            'quantity' => is_null($quantity) ? 0 : $quantity,
            'minimum' => 1,
            'subtract' => 1,
            'stock_status_id' => 7,
            'date_available' => date('Y-m-d H:i:s'),
            'manufacturer_id' => $model->getManufacturer($manufacturer, $model->getStores(null)),
            'shipping' => 1,
            'price' => $price,
            'points' => 0,
            'weight' => 0,
            'weight_class_id' => 1,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'length_class_id' => 1,
            'status' => $status,
            'tax_class_id' => $model->getTaxClass($tax_rate),
            'sort_order' => 1,
            'product_description' => array(
                $language_id => array(
                    'name' => $name,
                    'tag' => '',
                    'description' => $description,
                    'package_description' => $description,
                    'meta_title' => $name,
                    'meta_description' => $name,
                    'meta_keyword' => $name,
                )
            ),
            'product_category' => $model->getCategories($categories),
            'product_store' => $model->getStores($stores),
            'product_image' => array()
        );
    }

    public function productsToStore() {
        $this->load->model('tool/backup');
        $this->model_tool_backup->productsToStore();
    }

    public function trimCustomerNames() {
        $this->load->model('tool/backup');
        $this->model_tool_backup->trimCustomerNames();
    }

    public function ozer() {
        $data = array(
            array("code" => "500001", "name" => "25*35 CM RESİM DEFTERİ 120 GR","manufacturer" => "TALENS", "package_quantity" => "2", "price" => "17.50", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500002", "name" => "YAĞLI PASTEL BOYA 24 RENKLİ", "manufacturer" => "PANDA", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500003", "name" => "KURU BOYA 12 RENKLİ JUMBO BOY ÜÇGEN BEYAZ GÖVDE", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500004", "name" => "SULU BOYA 12 RENKLİ", "manufacturer" => "ALPİNO", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500005", "name" => "VİSA COLOR JUMBO KEÇELİ BOYA 24 RENKLİ", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500006", "name" => "3'LÜ SÜNGER FIRÇA SETİ", "manufacturer" => "LUNA", "package_quantity" => "1", "price" => "14.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500007", "name" => "TACK İT YAPIŞTIRICI", "manufacturer" => "FABER CASTELL", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500008", "name" => "RİCH FUNNY KİDS MULTİ AKRİLİK BOYA 12'Lİ SET", "manufacturer" => "FUNNY KİDS", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500009", "name" => "OYUN HAMURU 4 RENKLİ (KLASİK RENK)", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "12.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500010", "name" => "SERAMİK ÇAMURU 250 GR 4 ADET TERRACODA", "manufacturer" => "DARWİ", "package_quantity" => "4", "price" => "17.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500011", "name" => "FON KARTONU 10 RENKLİ 160 GR", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500012", "name" => "MAKAS", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "4.25", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500013", "name" => "BOYA ÖNLÜĞÜ", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "40.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500014", "name" => "SULUBOYA KABI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500015", "name" => "SOFT TOUCH RENKLİ FIRÇA SETİ", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "5 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500016", "name" => "KURU BOYA TÜP ŞEKLİNDE (24’LÜ)", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "42.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500017", "name" => "KEÇELİ KALEM (12’Lİ)", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500018", "name" => "TACK IT (YEŞİL – MAVİ)", "manufacturer" => "FABER CASTELL", "package_quantity" => "4", "price" => "12.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500019", "name" => "STICK YAPIŞTIRICI BİTKİSEL ÖZLÜ SOLVENTSİZ (22-gr)", "manufacturer" => "ALPİNO", "package_quantity" => "5", "price" => "8.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500020", "name" => "YAPIŞKANLI SİMLİ RENKLİ EVA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500021", "name" => "YAPIŞKANLI SİMSİZ RENKLİ EVA", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500022", "name" => "RENKLİ KEÇE (10’LU) (A-4 BOYUTUNDA)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500023", "name" => "UCU KÜT ÇOCUK MAKASI (SAĞ EL – SOL EL)", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500025", "name" => "KALEMTRAŞ (BÜYÜK – KÜÇÜK BÖLMELİ)", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500027", "name" => "PİPO TEMİZLEYİCİ (ŞÖNİL) SİMLİ", "manufacturer" => "", "package_quantity" => "1", "price" => "8.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500028", "name" => "PİPO TEMİZLEYİCİ (ŞÖNİL) SİMSİZ", "manufacturer" => "", "package_quantity" => "1", "price" => "8.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500029", "name" => "LASTİKLİ KALIN KARTON KUTU DOSYA", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500030", "name" => "BIC VELLADA YAZI TAHTASI SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500031", "name" => "RENKLİ GRAFON KAĞIDI", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500032", "name" => "RENKLİ A4 KAĞIT (100 ’LÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500033", "name" => "RENKLİ PON PON", "manufacturer" => "", "package_quantity" => "1", "price" => "10.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500034", "name" => "RENKLİ TÜY", "manufacturer" => "", "package_quantity" => "1", "price" => "10.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500035", "name" => "OYNAYAN GÖZ", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500036", "name" => "KURŞUN KALEM", "manufacturer" => "BİC", "package_quantity" => "5", "price" => "2.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500037", "name" => "BAŞLANGIÇ KALEMİ (SAĞ VE SOL EL KALEMİ)", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500038", "name" => "OYUN HAMURU 6 RENK", "manufacturer" => "ALPİNO", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500039", "name" => "POŞET DOSYA", "manufacturer" => "NOKİ", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500040", "name" => "PARMAK BOYASI 500-ML/HER ÖĞRENCİ İÇİN FARKLI 2 RENK", "manufacturer" => "FUNNY KİDS", "package_quantity" => "2", "price" => "35.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500041", "name" => "50*70 BOYUTUNDA FON KARTONU (10’LU) (160-GR)", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500042", "name" => "SIRT ÇANTASI", "manufacturer" => "", "package_quantity" => "1", "price" => "100.00", "tax" => 8, "class" => "5 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500001", "name" => "25*35 CM RESİM DEFTERİ 120 GR", "manufacturer" => "TALENS", "package_quantity" => "2", "price" => "17.50", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500002", "name" => "YAĞLI PASTEL BOYA 24 RENKLİ", "manufacturer" => "PANDA", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500003", "name" => "KURU BOYA 12 RENKLİ JUMBO BOY ÜÇGEN BEYAZ GÖVDE", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500004", "name" => "SULU BOYA 12 RENKLİ", "manufacturer" => "ALPİNO", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500005", "name" => "VİSA COLOR JUMBO KEÇELİ BOYA 24 RENKLİ", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500006", "name" => "3'LÜ SÜNGER FIRÇA SETİ", "manufacturer" => "LUNA", "package_quantity" => "1", "price" => "14.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500007", "name" => "TACK İT YAPIŞTIRICI", "manufacturer" => "FABER CASTELL", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500008", "name" => "RİCH FUNNY KİDS MULTİ AKRİLİK BOYA 12'Lİ SET", "manufacturer" => "FUNNY KİDS", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500009", "name" => "OYUN HAMURU 4 RENKLİ (KLASİK RENK)", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "12.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500010", "name" => "SERAMİK ÇAMURU 250 GR 4 ADET TERRACODA", "manufacturer" => "DARWİ", "package_quantity" => "4", "price" => "17.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500011", "name" => "FON KARTONU 10 RENKLİ 160 GR", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500012", "name" => "MAKAS", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "4.25", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500013", "name" => "BOYA ÖNLÜĞÜ", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "40.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500014", "name" => "SULUBOYA KABI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500015", "name" => "SOFT TOUCH RENKLİ FIRÇA SETİ", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "6 Yaş", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500016", "name" => "KURU BOYA TÜP ŞEKLİNDE (24’LÜ)", "manufacturer" => "FABER CASTELL", "package_quantity" => "1", "price" => "42.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500017", "name" => "KEÇELİ KALEM (12’Lİ)", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500018", "name" => "TACK IT (YEŞİL – MAVİ)", "manufacturer" => "FABER CASTELL", "package_quantity" => "4", "price" => "12.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500019", "name" => "STICK YAPIŞTIRICI BİTKİSEL ÖZLÜ SOLVENTSİZ (22-gr)", "manufacturer" => "ALPİNO", "package_quantity" => "5", "price" => "8.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500020", "name" => "YAPIŞKANLI SİMLİ RENKLİ EVA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500021", "name" => "YAPIŞKANLI SİMSİZ RENKLİ EVA", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500022", "name" => "RENKLİ KEÇE (10’LU) (A-4 BOYUTUNDA)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500023", "name" => "UCU KÜT ÇOCUK MAKASI (SAĞ EL – SOL EL)", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500025", "name" => "KALEMTRAŞ (BÜYÜK – KÜÇÜK BÖLMELİ)", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500027", "name" => "PİPO TEMİZLEYİCİ (ŞÖNİL) SİMLİ", "manufacturer" => "", "package_quantity" => "1", "price" => "8.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500028", "name" => "PİPO TEMİZLEYİCİ (ŞÖNİL) SİMSİZ", "manufacturer" => "", "package_quantity" => "1", "price" => "8.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500029", "name" => "LASTİKLİ KALIN KARTON KUTU DOSYA", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500030", "name" => "BIC VELLADA YAZI TAHTASI SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500031", "name" => "RENKLİ GRAFON KAĞIDI", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500032", "name" => "RENKLİ A4 KAĞIT (100 ’LÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500033", "name" => "RENKLİ PON PON", "manufacturer" => "", "package_quantity" => "1", "price" => "10.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500034", "name" => "RENKLİ TÜY", "manufacturer" => "", "package_quantity" => "1", "price" => "10.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500035", "name" => "OYNAYAN GÖZ", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500036", "name" => "KURŞUN KALEM", "manufacturer" => "BİC", "package_quantity" => "5", "price" => "2.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500037", "name" => "BAŞLANGIÇ KALEMİ (SAĞ VE SOL EL KALEMİ)", "manufacturer" => "BİC", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500038", "name" => "OYUN HAMURU 6 RENK", "manufacturer" => "ALPİNO", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500039", "name" => "POŞET DOSYA", "manufacturer" => "NOKİ", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500040", "name" => "PARMAK BOYASI 500-ML/HER ÖĞRENCİ İÇİN FARKLI 2 RENK", "manufacturer" => "FUNNY KİDS", "package_quantity" => "2", "price" => "35.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500041", "name" => "50*70 BOYUTUNDA FON KARTONU (10’LU) (160-GR)", "manufacturer" => "ADEL", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500042", "name" => "SIRT ÇANTASI", "manufacturer" => "", "package_quantity" => "1", "price" => "100.00", "tax" => 8, "class" => "6 Yaş", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500085", "name" => "SINAV READING  MACHINE", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "298.00", "tax" => 0, "class" => "1.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500086", "name" => "ARKADAŞ İSTİYORUM", "manufacturer" => "YEŞİL DİNOZOR", "package_quantity" => "1", "price" => "24.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500087", "name" => "FOKS (ATATÜRK’LE DEĞERLEREĞİTİMİ-2)", "manufacturer" => "ALALMA", "package_quantity" => "1", "price" => "25.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500088", "name" => "KEDİ ADASI", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "19.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500089", "name" => "KÜTÜPHANE FARESİ- BİR DOSTUN ÖYKÜSÜ", "manufacturer" => "FİNAL KÜLTÜR SANAT", "package_quantity" => "1", "price" => "20.50", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500090", "name" => "PAPATYA KARLI BİR GÜN", "manufacturer" => "SİA", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500091", "name" => "TUATARA İLE ZAMANIN KEŞFİ", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500092", "name" => "YAVRU AHTAPOT OLMAK ÇOK ZOR", "manufacturer" => "YKY", "package_quantity" => "1", "price" => "25.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500093", "name" => "YÜZYÜZ", "manufacturer" => "ELMA YAYINEVİ", "package_quantity" => "1", "price" => "32.00", "tax" => 0, "class" => "1.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500095", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500096", "name" => "FABER CASTELL TAKE-IT", "manufacturer" => "", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500097", "name" => "LARART 40X40 PROFESSIONAL SERİ TUVAL", "manufacturer" => "", "package_quantity" => "1", "price" => "27.50", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500098", "name" => "RICH FUNNY KIDS MULTI MIX AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500099", "name" => "RESİM PALETİ 20cm", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500100", "name" => "RICH DÜZ UÇLU SENTETİK FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500101", "name" => "DARWİ 500gr SERAMİK KİLİ TERRACOTTA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500103", "name" => "TALENS 25X35 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500105", "name" => "FABER CASTELL GOLDFABER DERECELİ KALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500107", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500108", "name" => "FABER CASTELL 12 RENK JUMBO KURU BOYA ÜÇGEN BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500109", "name" => "BİC VİSACOLOR 24 RENK JUMBO KEÇELİ KALEM", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500110", "name" => "ADEL FON KARTONU 10 RENK KARIŞIK 50x70 rulo", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500111", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "2", "price" => "16.00", "tax" => 8, "class" => "1.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500112", "name" => "60 SAYFA HARİTA METOD DEFTERİ ÇİZGİLİ", "manufacturer" => "", "package_quantity" => "1", "price" => "9.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500113", "name" => "60 SAYFA HARİTA METOD DEFTERİ KARELİ", "manufacturer" => "", "package_quantity" => "1", "price" => "9.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500114", "name" => "1 ORTALI GÜZEL YAZI HARİTA METOD DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "10.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500115", "name" => "1 ORTALI ÇİZGİLİ HARİTA METOD DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500116", "name" => "MAGAZİNLİK (2 KIRMIZI 1 BEYAZ)", "manufacturer" => "", "package_quantity" => "3", "price" => "22.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500117", "name" => "BAŞLIK KALEMİ (KIRMIZI-MAVİ-YEŞİL)", "manufacturer" => "", "package_quantity" => "6", "price" => "2.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500118", "name" => "KURŞUN KALEM", "manufacturer" => "", "package_quantity" => "6", "price" => "2.25", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "2", "price" => "3.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500120", "name" => "KALEMTRAŞ (ÇİFT HANELİ)", "manufacturer" => "", "package_quantity" => "2", "price" => "6.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500121", "name" => "CETVEL 30cm PLASTİK", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500122", "name" => "FABER CASTELL BLUE TACK", "manufacturer" => "", "package_quantity" => "3", "price" => "12.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500123", "name" => "ALPİNO STICK YAPIŞTIRICI 44gr", "manufacturer" => "", "package_quantity" => "5", "price" => "15.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500125", "name" => "ŞEFFAF BANT 12X33", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500126", "name" => "TAHTA KALEMİ (KIRMIZI - SİYAH - MAVİ) 2'ŞER ADET", "manufacturer" => "", "package_quantity" => "6", "price" => "5.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500127", "name" => "ADEL KURU BOYA KALEMİ 24 RENK", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500130", "name" => "ÇITÇIT DOSYA", "manufacturer" => "", "package_quantity" => "3", "price" => "2.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500131", "name" => "İSİM ETİKETİ ", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500132", "name" => "RENKLİ A4 KAĞIT 100'LÜ KARIŞIK", "manufacturer" => "", "package_quantity" => "1", "price" => "23.50", "tax" => 8, "class" => "1.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500133", "name" => "SINAV READING  MACHINE", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "298.00", "tax" => 0, "class" => "2.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500134", "name" => "BAYAN ŞEFTALİ VE ALYA DEĞİRMENDE", "manufacturer" => "BİLGİ YAYINEVİ", "package_quantity" => "1", "price" => "17.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500135", "name" => "HIZLI OKUYAN KURTÇUK", "manufacturer" => "EPSİLON", "package_quantity" => "1", "price" => "27.50", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500136", "name" => "CANI SIKILAN ZÜRAFA MEKTUP YAZIYOR", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "11.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500137", "name" => "GEZGİN BEZGİN", "manufacturer" => "YEŞİL DİNOZOR", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500138", "name" => "KARACA VE YÜRÜYEN KÖŞK", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "28.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500139", "name" => "KAYIP MADALYANIN İZİNDE", "manufacturer" => "ELMA YAYINEVİ", "package_quantity" => "1", "price" => "16.50", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500140", "name" => "ORMAN KALPLİ ŞEHİR", "manufacturer" => "FİNAL KÜLTÜR SANAT YAY", "package_quantity" => "1", "price" => "55.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500141", "name" => "SAKIZ AĞACI", "manufacturer" => "ELMA YAYINEVİ", "package_quantity" => "1", "price" => "17.00", "tax" => 0, "class" => "2.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500095", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500096", "name" => "FABER CASTELL TAKE-IT", "manufacturer" => "", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500097", "name" => "LARART 40X40 PROFESSIONAL SERİ TUVAL", "manufacturer" => "", "package_quantity" => "1", "price" => "27.50", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500098", "name" => "RICH FUNNY KIDS MULTI MIX AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500099", "name" => "RESİM PALETİ 20cm", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500100", "name" => "RICH DÜZ UÇLU SENTETİK FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500101", "name" => "DARWİ 500gr SERAMİK KİLİ TERRACOTTA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500103", "name" => "TALENS 25X35 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500105", "name" => "FABER CASTELL GOLDFABER DERECELİ KALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500107", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500108", "name" => "FABER CASTELL 12 RENK JUMBO KURU BOYA ÜÇGEN BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500109", "name" => "BİC VİSACOLOR 24 RENK JUMBO KEÇELİ KALEM", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500110", "name" => "ADEL FON KARTONU 10 RENK KARIŞIK 50x70 rulo", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500111", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "2", "price" => "16.00", "tax" => 8, "class" => "2.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500112", "name" => "60 SAYFA HARİTA METOD DEFTERİ ÇİZGİLİ", "manufacturer" => "", "package_quantity" => "2", "price" => "9.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500113", "name" => "60 SAYFA HARİTA METOD DEFTERİ KARELİ", "manufacturer" => "", "package_quantity" => "1", "price" => "9.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500164", "name" => "ATA ATASÖZLERİ VE DEYİMLER SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500117", "name" => "BAŞLIK KALEMİ (KIRMIZI-MAVİ-YEŞİL)", "manufacturer" => "", "package_quantity" => "6", "price" => "2.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500118", "name" => "KURŞUN KALEM", "manufacturer" => "", "package_quantity" => "6", "price" => "2.25", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "2", "price" => "3.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500120", "name" => "KALEMTRAŞ (ÇİFT HANELİ)", "manufacturer" => "", "package_quantity" => "2", "price" => "6.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500121", "name" => "CETVEL 30cm PLASTİK", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500122", "name" => "FABER CASTELL BLUE TACK", "manufacturer" => "", "package_quantity" => "3", "price" => "12.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500123", "name" => "ALPİNO STICK YAPIŞTIRICI 44gr", "manufacturer" => "", "package_quantity" => "5", "price" => "15.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500172", "name" => "ZARF DOSYA", "manufacturer" => "", "package_quantity" => "3", "price" => "2.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500125", "name" => "ŞEFFAF BANT 12X33", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500127", "name" => "ADEL KURU BOYA KALEMİ 24 RENK", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500126", "name" => "TAHTA KALEMİ (KIRMIZI - SİYAH - MAVİ) 2'ŞER ADET", "manufacturer" => "", "package_quantity" => "6", "price" => "5.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500177", "name" => "İLETKİ", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500178", "name" => "ATAŞ NO:4", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500180", "name" => "İSİM ETİKETİ 24 LÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500181", "name" => "MAGAZİNLİK (2 SİYAH 2 BEYAZ)", "manufacturer" => "", "package_quantity" => "4", "price" => "22.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500182", "name" => "SUNUM DOSYASI", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "2.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500183", "name" => "SINAV READING  MACHINE", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "298.00", "tax" => 0, "class" => "3.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500184", "name" => "Tim und Tina 1 Lebruch", "manufacturer" => "ERA DİL", "package_quantity" => "1", "price" => "150.00", "tax" => 0, "class" => "3.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500185", "name" => "Tim und Tina 1 Arbeitsbuch", "manufacturer" => "ERA DİL", "package_quantity" => "1", "price" => "0.00", "tax" => 0, "class" => "3.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500186", "name" => "7X9=EYVAH!", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "11.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500187", "name" => "DÜNYA NEFES ALSIN", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "15.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500188", "name" => "ALTIN SAÇLI ÇOCUK", "manufacturer" => "MASKE KİTAP", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500189", "name" => "FEDOR AMCA", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "15.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500190", "name" => "ZAMAN BİSİKLETİ-1", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "16.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500191", "name" => "BUNLAR ONLAR MI?", "manufacturer" => "YEŞİL DİNOZOR", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500192", "name" => "HAYATI KEŞFETMEK İSTEYEN PENGUEN", "manufacturer" => "HAYYKİTAP", "package_quantity" => "1", "price" => "11.11", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500193", "name" => "SİHİRLİ AĞAÇ EVİ DİNOZORLAR VADİSİNDE", "manufacturer" => "DOMİNGO YAYINEVİ", "package_quantity" => "1", "price" => "16.00", "tax" => 0, "class" => "3.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500095", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500096", "name" => "FABER CASTELL TAKE-IT", "manufacturer" => "", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500097", "name" => "LARART 40X40 PROFESSIONAL SERİ TUVAL", "manufacturer" => "", "package_quantity" => "1", "price" => "27.50", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500098", "name" => "RICH FUNNY KIDS MULTI MIX AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500099", "name" => "RESİM PALETİ 20cm", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500100", "name" => "RICH DÜZ UÇLU SENTETİK FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500101", "name" => "DARWİ 500gr SERAMİK KİLİ TERRACOTTA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500103", "name" => "TALENS 25X35 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500105", "name" => "FABER CASTELL GOLDFABER DERECELİ KALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500107", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500108", "name" => "FABER CASTELL 12 RENK JUMBO KURU BOYA ÜÇGEN BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500109", "name" => "BİC VİSACOLOR 24 RENK JUMBO KEÇELİ KALEM", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500110", "name" => "ADEL FON KARTONU 10 RENK KARIŞIK 50x70 rulo", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500111", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "2", "price" => "16.00", "tax" => 8, "class" => "3.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500112", "name" => "60 SAYFA HARİTA METOD DEFTERİ ÇİZGİLİ", "manufacturer" => "", "package_quantity" => "2", "price" => "9.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500113", "name" => "60 SAYFA HARİTA METOD DEFTERİ KARELİ", "manufacturer" => "", "package_quantity" => "2", "price" => "9.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500164", "name" => "ATA ATASÖZLERİ VE DEYİMLER SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500117", "name" => "BAŞLIK KALEMİ (KIRMIZI-MAVİ-YEŞİL)", "manufacturer" => "", "package_quantity" => "6", "price" => "2.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500118", "name" => "KURŞUN KALEM", "manufacturer" => "", "package_quantity" => "6", "price" => "2.25", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "2", "price" => "3.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500120", "name" => "KALEMTRAŞ (ÇİFT HANELİ)", "manufacturer" => "", "package_quantity" => "2", "price" => "6.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500121", "name" => "CETVEL 30cm PLASTİK", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500122", "name" => "FABER CASTELL BLUE TACK", "manufacturer" => "", "package_quantity" => "3", "price" => "12.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500123", "name" => "ALPİNO STICK YAPIŞTIRICI 44gr", "manufacturer" => "", "package_quantity" => "6", "price" => "15.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500178", "name" => "ATAŞ NO:4", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500125", "name" => "ŞEFFAF BANT 12X33", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500127", "name" => "ADEL KURU BOYA KALEMİ 24 RENK", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500126", "name" => "TAHTA KALEMİ (KIRMIZI - SİYAH - MAVİ) 2'ŞER ADET", "manufacturer" => "", "package_quantity" => "6", "price" => "5.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500177", "name" => "İLETKİ", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500180", "name" => "İSİM ETİKETİ 24 LÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500181", "name" => "MAGAZİNLİK (2 SİYAH 2 BEYAZ)", "manufacturer" => "", "package_quantity" => "4", "price" => "22.50", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500233", "name" => "A4 FOTOKOPİ KAĞIDI 500'LÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "3.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500234", "name" => "SINAV READING  MACHINE", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "298.00", "tax" => 0, "class" => "4.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500235", "name" => "OLIWER TWIST - CHARLES DICKENS", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "62.00", "tax" => 0, "class" => "4.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500236", "name" => "MAGIC FINGER - ROALD DAHL", "manufacturer" => "DURU ELT", "package_quantity" => "1", "price" => "57.00", "tax" => 0, "class" => "4.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500237", "name" => "Tim und Tina 2 Lebruch", "manufacturer" => "ERA DİL", "package_quantity" => "1", "price" => "150.00", "tax" => 0, "class" => "4.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500238", "name" => "Tim und Tina 2 Arbeitsbuch", "manufacturer" => "ERA DİL", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "4.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500239", "name" => "SAYISAL ÇOCUK", "manufacturer" => "BİLGİ YAYINLARI", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500240", "name" => "ELLERİYLE GÖREN ÇOCUK", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "11.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500241", "name" => "DOĞA MECLİSİ ORMANLAR YANMASIN", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "13.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500242", "name" => "GEÇMİŞE TIRMANAN MERDİVEN", "manufacturer" => "GÜNIŞIĞI KİTAPLIĞI", "package_quantity" => "1", "price" => "24.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500243", "name" => "DAHİLER SINIFI- MOZART MÜZİĞİN DÂHİSİ", "manufacturer" => "DOMİNGO YAYINEVİ", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500244", "name" => "DAHİLER SINIFI- MARIE CURIE: ATOM KADIN", "manufacturer" => "DOMİNGO YAYINEVİ", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500245", "name" => "HAYALLERE İLK ADIM", "manufacturer" => "MASKE KİTAP", "package_quantity" => "1", "price" => "24.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500246", "name" => "DÜNYAYI BİSİKLETLE DOLAŞAN ÇOCUK", "manufacturer" => "BEYAZ BALİNA YAY", "package_quantity" => "1", "price" => "14.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500247", "name" => "SAKIZ SARDUNYA", "manufacturer" => "DOĞAN VE EGMONT", "package_quantity" => "1", "price" => "29.00", "tax" => 0, "class" => "4.SINIF", "package" => "TÜRKÇE OKUMA KİTAP", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500095", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500096", "name" => "FABER CASTELL TAKE-IT", "manufacturer" => "", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500097", "name" => "LARART 40X40 PROFESSIONAL SERİ TUVAL", "manufacturer" => "", "package_quantity" => "1", "price" => "27.50", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500098", "name" => "RICH FUNNY KIDS MULTI MIX AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500099", "name" => "RESİM PALETİ 20cm", "manufacturer" => "", "package_quantity" => "1", "price" => "4.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500100", "name" => "RICH DÜZ UÇLU SENTETİK FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500101", "name" => "DARWİ 500gr SERAMİK KİLİ TERRACOTTA", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500094", "name" => "TALENS 35x50 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500103", "name" => "TALENS 25X35 120gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500105", "name" => "FABER CASTELL GOLDFABER DERECELİ KALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500107", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500108", "name" => "FABER CASTELL 12 RENK JUMBO KURU BOYA ÜÇGEN BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500109", "name" => "BİC VİSACOLOR 24 RENK JUMBO KEÇELİ KALEM", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500110", "name" => "ADEL FON KARTONU 10 RENK KARIŞIK 50x70 rulo", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500111", "name" => "ALPİNO BİTKİSEL ÖZLÜ 22gr STICK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "2", "price" => "16.00", "tax" => 8, "class" => "4.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500266", "name" => "80 SAYFA HARİTA METOD DEFTERİ ÇİZGİSİZ", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500267", "name" => "80 SAYFA HARİTA METOD DEFTERİ ÇİZGİLİ", "manufacturer" => "", "package_quantity" => "2", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500268", "name" => "80 SAYFA HARİTA METOD DEFTERİ KARELİ", "manufacturer" => "", "package_quantity" => "3", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500164", "name" => "ATA ATASÖZLERİ VE DEYİMLER SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500272", "name" => "MAGAZİNLİK", "manufacturer" => "", "package_quantity" => "4", "price" => "22.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500117", "name" => "BAŞLIK KALEMİ (KIRMIZI-MAVİ-YEŞİL)", "manufacturer" => "", "package_quantity" => "6", "price" => "2.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500118", "name" => "KURŞUN KALEM", "manufacturer" => "", "package_quantity" => "6", "price" => "2.25", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500026", "name" => "SİLGİ", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500120", "name" => "KALEMTRAŞ (ÇİFT HANELİ)", "manufacturer" => "", "package_quantity" => "1", "price" => "6.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500122", "name" => "FABER CASTELL BLUE TACK", "manufacturer" => "", "package_quantity" => "3", "price" => "12.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500123", "name" => "ALPİNO STICK YAPIŞTIRICI 44gr", "manufacturer" => "", "package_quantity" => "6", "price" => "15.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500125", "name" => "ŞEFFAF BANT 12X33", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500280", "name" => "TAHTA KALEMİ (KIRMIZI - SİYAH- MAVİ)", "manufacturer" => "", "package_quantity" => "9", "price" => "5.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500127", "name" => "ADEL KURU BOYA KALEMİ 24 RENK", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500024", "name" => "KALEM KUTUSU", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500131", "name" => "İSİM ETİKETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500285", "name" => "RENKLİ A4 KAĞIT 1 TOP", "manufacturer" => "", "package_quantity" => "1", "price" => "23.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500233", "name" => "A4 FOTOKOPİ KAĞIDI 500'LÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.00", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500287", "name" => "ZARF DOSYA (İNGİLİZCE)", "manufacturer" => "", "package_quantity" => "1", "price" => "2.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500272", "name" => "MAGAZİNLİK", "manufacturer" => "", "package_quantity" => "4", "price" => "22.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500289", "name" => "RENKLİ FON KARTONU 50X70 10 RENK", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "4.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500290", "name" => "VOICES 5 PUPILS BOOK + ACTIVITY BOOK", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "179.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500291", "name" => "MY BRAIN POP JUNIOR", "manufacturer" => "GLOBED", "package_quantity" => "1", "price" => "194.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500292", "name" => "CAMBRIDGE LEARNER'S DICTIONARY ", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "169.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500293", "name" => "ENGLISH BENCHMARK  ( 2 adet)", "manufacturer" => "PEARSON", "package_quantity" => "1", "price" => "300.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500294", "name" => "POWER UP 5 /PUPIL'S BOOK ", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "260.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500295", "name" => "OWN IT 1 STUDENT BOOK", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "246.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500296", "name" => "OWN IT 2 STUDENT BOOK", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "246.00", "tax" => 0, "class" => "5.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500297", "name" => "Neu Deutsch Talent Pack A1.1", "manufacturer" => "UNIVERSAL", "package_quantity" => "1", "price" => "225.00", "tax" => 0, "class" => "5.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500298", "name" => "BUNUN ADI FİNDEL", "manufacturer" => "GÜNIŞIĞI KİTAPLIĞI", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500247", "name" => "SAKIZ SARDUNYA", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "29.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500300", "name" => "MUSTAFA KEMAL'İN KAYIP SESLERİNİN İZİNDE", "manufacturer" => "BİLGİ YAY", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500301", "name" => "UZAY KAMPI MACERALARI", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "30.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500302", "name" => "80 GÜNDE DÜNYA TURU", "manufacturer" => "BEYAZ BALİNA", "package_quantity" => "1", "price" => "13.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500303", "name" => "101 ATASÖZÜ 101 ÖYKÜ", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "19.50", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500304", "name" => "HARİTADA KAYBOLMAK", "manufacturer" => "GÜNIŞIĞI KİTAPLIĞI", "package_quantity" => "1", "price" => "23.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500305", "name" => "SANDIK SEPET ANKARA", "manufacturer" => "ELMA ÇOCUK", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500306", "name" => "EMİLİA AĞAÇTA", "manufacturer" => "TİMAŞ GENÇ", "package_quantity" => "1", "price" => "17.50", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500307", "name" => "EVE GİDEN KÜÇÜK TREN", "manufacturer" => "GÜNIŞIĞI KİTAPLIĞI", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500308", "name" => "RESİMLİ HAYAL ANSİKLOPEDİSİ", "manufacturer" => "ELMA ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500309", "name" => "MOBY DICK", "manufacturer" => "İŞ BANKASI", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500310", "name" => "OLMAYAN ÜLKE", "manufacturer" => "EPSİLON", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500311", "name" => "EĞLENCELİ ŞEYLER KİTABI", "manufacturer" => "BİLGİ YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500312", "name" => "KÜÇÜK PRENS", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500313", "name" => "MIEKO VE BEŞİNCİ HAZİNE", "manufacturer" => "BEYAZ BALİNA", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500314", "name" => "KRALİÇEYİ KURTARMAK", "manufacturer" => "GÜNIŞIĞI KİTAPLIĞI", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500315", "name" => "HEZARFEN", "manufacturer" => "ELMA ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "5.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500316", "name" => "ALPİNO 12 RENK SULU BOYA TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500317", "name" => "BRUYUNZELL TALENS METAL KUTU 6'LI KARAKALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "40.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500318", "name" => "BİC FIRÇA UÇLU KEÇELİ KALEM 10'LU", "manufacturer" => "", "package_quantity" => "1", "price" => "42.50", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500319", "name" => "TALENS PANDA 24 RENK YAĞLI PASTEL BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500320", "name" => "FUNNY KİDS MULTIMAX 12 RENK AKRİLİK BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500321", "name" => "TALENS 35X50 RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500322", "name" => "FOTOBLOK 70X100 SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500323", "name" => "FOTOBLOK 70X100 BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500324", "name" => "RICH KARIŞIK FIRÇA SETİ 7'Lİ (ZEMİN FIRÇALI)", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500325", "name" => "LARART TUVAL 35X50", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500326", "name" => "PATAFİX 50gr (FABER CASTELL)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500327", "name" => "KAĞIT BANT", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500328", "name" => "ALPİNO 22gr BİTKİSEL ÖZLÜ STİCK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "5.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500331", "name" => "ATA DEYİMLER VE ATASÖZLERİ SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500332", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (TÜRKÇE)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500333", "name" => "120 YAPRAK SPR. PP. KAP. DEFTER KARELİ (MATEMATİK)", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500334", "name" => "ÇİZİM TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500335", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ YADA ÇİZ) SOSYAL BİL.", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500336", "name" => "ATA TARİH ATLASI", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500337", "name" => "TÜRKİYE ŞEHİRLER PUZZLE", "manufacturer" => "", "package_quantity" => "1", "price" => "", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500338", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ) FEN BİLİMLERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500339", "name" => "RENKLİ KALEMLER 12 RENK ADEL", "manufacturer" => "", "package_quantity" => "1", "price" => "13.50", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500340", "name" => "ALPİNO STİCK YAPIŞTIRICI 22gr", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500342", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (DİN KÜLTÜRÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "5.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500343", "name" => "POWER UP 6/PUPIL'S BOOK", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "260.00", "tax" => 0, "class" => "6.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500344", "name" => "VOICES 6 PUPILS BOOK + ACTIVITY BOOK", "manufacturer" => "CAMBRIDGE", "package_quantity" => "1", "price" => "179.00", "tax" => 0, "class" => "6.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500345", "name" => "MY BRAIN POP ", "manufacturer" => "GLOBED", "package_quantity" => "1", "price" => "194.00", "tax" => 0, "class" => "6.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500346", "name" => "ENGLISH BENCHMARK  ( 2 adet)", "manufacturer" => "PEARSON", "package_quantity" => "1", "price" => "300.00", "tax" => 0, "class" => "6.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500347", "name" => "Neu Deutsch Talent Pack A1.2", "manufacturer" => "UNIVERSAL", "package_quantity" => "1", "price" => "225.00", "tax" => 0, "class" => "6.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500348", "name" => "Eyvah Kitap", "manufacturer" => "GÜNIŞIĞI", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500349", "name" => "Serçe Kuş", "manufacturer" => "BEYAN YAY.", "package_quantity" => "1", "price" => "12.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500350", "name" => "Dersimiz Atatürk", "manufacturer" => "BİLGİ YAY.", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500351", "name" => "Chalie’nin Çikolata Fabrikası (Roald Dahl)", "manufacturer" => "BEYAZ BALİNA", "package_quantity" => "1", "price" => "9.90", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500352", "name" => "Yeşil Adanın Çocukları", "manufacturer" => "GENÇ TİMAŞ", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500353", "name" => "Bir Pekin Ördeğinin Tam 15 Yıl 5 Ay Süren Yolculuğu", "manufacturer" => "KELİME YAY.", "package_quantity" => "1", "price" => "25.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500354", "name" => "Çöp Plaza", "manufacturer" => "TUDEM YAY.", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500355", "name" => "Olduğun Yerde Kal", "manufacturer" => "TUDEM YAY.", "package_quantity" => "1", "price" => "27.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500356", "name" => "Pal Sokağı Çocukları", "manufacturer" => "YKY", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500357", "name" => "MATİLDA", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500358", "name" => "ZAMAN KAPISI (ULYSSES MORE)", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500359", "name" => "BÜYÜK ATATÜRK'TEN KÜÇÜK ÖYKÜLER", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>,SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500360", "name" => "DEYİMLER VE ÖYKÜLER 1", "manufacturer" => "ZAFER YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500361", "name" => "DEYİMLER VE ÖYKÜLER 2", "manufacturer" => "ZAFER YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500362", "name" => "UÇAN SINIF", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500363", "name" => "HAYAL TAKIMI", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500364", "name" => "SAVAŞ ATI", "manufacturer" => "TUDEM", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500365", "name" => "GÜNLE YARIŞAN YARIŞCI", "manufacturer" => "ELMA ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "6.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500321", "name" => "TALENS 35X50 RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500323", "name" => "FOTOBLOK 70X100 BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500326", "name" => "PATAFİX 50gr (FABER CASTELL)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500328", "name" => "ALPİNO 22gr BİTKİSEL ÖZLÜ STİCK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500325", "name" => "LARART TUVAL 35X50", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500316", "name" => "ALPİNO 12 RENK SULU BOYA TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500317", "name" => "BRUYUNZELL TALENS METAL KUTU 6'LI KARAKALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "40.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500318", "name" => "BİC FIRÇA UÇLU KEÇELİ KALEM 10'LU", "manufacturer" => "", "package_quantity" => "1", "price" => "42.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500319", "name" => "TALENS PANDA 24 RENK YAĞLI PASTEL BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500320", "name" => "FUNNY KİDS MULTIMAX 12 RENK AKRİLİK BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500321", "name" => "TALENS 35X50 RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500322", "name" => "FOTOBLOK 70X100 SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500323", "name" => "FOTOBLOK 70X100 BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500324", "name" => "RICH KARIŞIK FIRÇA SETİ 7'Lİ (ZEMİN FIRÇALI)", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500325", "name" => "LARART TUVAL 35X50", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500326", "name" => "PATAFİX 50gr (FABER CASTELL)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500327", "name" => "KAĞIT BANT", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500328", "name" => "ALPİNO 22gr BİTKİSEL ÖZLÜ STİCK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "6.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500331", "name" => "ATA DEYİMLER VE ATASÖZLERİ SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500332", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (TÜRKÇE)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500388", "name" => "120 YAPRAK SPR. PP. KAP. DEFTER KARELİ (MATEMATİK)", "manufacturer" => "", "package_quantity" => "1", "price" => "19.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500334", "name" => "ÇİZİM TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500335", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ YADA ÇİZ) SOSYAL BİL.", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500336", "name" => "ATA TARİH ATLASI", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500338", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ) FEN BİLİMLERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500339", "name" => "RENKLİ KALEMLER 12 RENK ADEL", "manufacturer" => "", "package_quantity" => "1", "price" => "13.50", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500340", "name" => "ALPİNO STİCK YAPIŞTIRICI 22gr", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500342", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (DİN KÜLTÜRÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "6.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500397", "name" => "NEW PULSE 4 STUDENT BOOK", "manufacturer" => "MACMILLAN", "package_quantity" => "1", "price" => "295.00", "tax" => 0, "class" => "7.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500398", "name" => "ACHIEVE 3000", "manufacturer" => "GLOBED", "package_quantity" => "1", "price" => "276.00", "tax" => 0, "class" => "7.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500399", "name" => "ENGLISH BENCHMARK  ( 2 adet)", "manufacturer" => "PEARSON", "package_quantity" => "1", "price" => "300.00", "tax" => 0, "class" => "7.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500400", "name" => "Neu Deutsch Talent Pack A1.2", "manufacturer" => "UNIVERSAL", "package_quantity" => "1", "price" => "225.00", "tax" => 0, "class" => "7.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500401", "name" => "Martı Jonathan Livingston", "manufacturer" => "EPSİLON", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500402", "name" => "Kapiland’ın Kobayları", "manufacturer" => "TUDEM", "package_quantity" => "1", "price" => "27.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500403", "name" => "Çocukluk Ne Güzel Şey", "manufacturer" => "EPSİLON", "package_quantity" => "1", "price" => "23.50", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500404", "name" => "Sherlock, Lüpen ve Ben- Siyahlı Kadın ", "manufacturer" => "DOĞAN EGMONT", "package_quantity" => "1", "price" => "48.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500405", "name" => "Çizgili Pijamalı Çocuk", "manufacturer" => "TUDEM", "package_quantity" => "1", "price" => "30.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500406", "name" => "Can Dostum", "manufacturer" => "BEYAZ BALİNA", "package_quantity" => "1", "price" => "19.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500407", "name" => "İnsan Ne ile Yaşar", "manufacturer" => "TİMAŞ", "package_quantity" => "1", "price" => "7.50", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500408", "name" => "Çanakkale Geçilmez", "manufacturer" => "BİLGİ YAY", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500409", "name" => "İçimdeki Müzik", "manufacturer" => "GENÇ TİMAŞ", "package_quantity" => "1", "price" => "25.00", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500410", "name" => "KARA OKLAR ÇETESİ BÜYÜK MACERA ", "manufacturer" => "ELMA ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>,SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500411", "name" => "Kendi Kutup Yıldızını Bul", "manufacturer" => "TULGAR ALFA YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>,SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500412", "name" => "Büyük Atatürk’ten Küçük Öyküler 2", "manufacturer" => "CAN COCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>,SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500413", "name" => "Üç Silahşörler", "manufacturer" => "GENÇ TİMAŞ", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500414", "name" => "Yüreğinin Götürdüğü Yere Git", "manufacturer" => "CAN COCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500415", "name" => "Deyimler ve Öyküleri 3", "manufacturer" => "ZAFER YAY.", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500416", "name" => "Deyimler ve Öyküleri 4", "manufacturer" => "ZAFER YAY.", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500417", "name" => "Toprak Ana", "manufacturer" => "ÖTÜKEN YAY.", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "7.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500321", "name" => "TALENS 35X50 RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500323", "name" => "FOTOBLOK 70X100 BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500326", "name" => "PATAFİX 50gr (FABER CASTELL)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500328", "name" => "ALPİNO 22gr BİTKİSEL ÖZLÜ STİCK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500325", "name" => "LARART TUVAL 35X50", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( ESKİ ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500316", "name" => "ALPİNO 12 RENK SULU BOYA TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500317", "name" => "BRUYUNZELL TALENS METAL KUTU 6'LI KARAKALEM SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "40.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500318", "name" => "BİC FIRÇA UÇLU KEÇELİ KALEM 10'LU", "manufacturer" => "", "package_quantity" => "1", "price" => "42.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500319", "name" => "TALENS PANDA 24 RENK YAĞLI PASTEL BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500320", "name" => "FUNNY KİDS MULTIMAX 12 RENK AKRİLİK BOYA", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500321", "name" => "TALENS 35X50 RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500322", "name" => "FOTOBLOK 70X100 SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500323", "name" => "FOTOBLOK 70X100 BEYAZ", "manufacturer" => "", "package_quantity" => "1", "price" => "32.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500324", "name" => "RICH KARIŞIK FIRÇA SETİ 7'Lİ (ZEMİN FIRÇALI)", "manufacturer" => "", "package_quantity" => "1", "price" => "65.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500325", "name" => "LARART TUVAL 35X50", "manufacturer" => "", "package_quantity" => "1", "price" => "30.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500326", "name" => "PATAFİX 50gr (FABER CASTELL)", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500327", "name" => "KAĞIT BANT", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500328", "name" => "ALPİNO 22gr BİTKİSEL ÖZLÜ STİCK YAPIŞTIRICI", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "7.SINIF", "package" => "GÖRSEL SANATLAR SETİ ( YENİ KAYIT ÖĞRENCİLER)", "categories" => "GÖRSEL ,SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500331", "name" => "ATA DEYİMLER VE ATASÖZLERİ SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500332", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (TÜRKÇE)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500388", "name" => "120 YAPRAK SPR. PP. KAP. DEFTER KARELİ (MATEMATİK)", "manufacturer" => "", "package_quantity" => "1", "price" => "19.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500334", "name" => "ÇİZİM TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500335", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ YADA ÇİZ) SOSYAL BİL.", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500336", "name" => "ATA TARİH ATLASI", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500338", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ) FEN BİLİMLERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500339", "name" => "RENKLİ KALEMLER 12 RENK ADEL", "manufacturer" => "", "package_quantity" => "1", "price" => "13.50", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500340", "name" => "ALPİNO STİCK YAPIŞTIRICI 22gr", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500342", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (DİN KÜLTÜRÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "7.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500449", "name" => "MORE & MORE 8 SELFISH TEST", "manufacturer" => "KURMAY ELT", "package_quantity" => "1", "price" => "26.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500450", "name" => "MORE & MORE 8  WORKSHEETS", "manufacturer" => "KURMAY ELT", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500451", "name" => "MORE & MORE 8  FAME TEST BOOK", "manufacturer" => "KURMAY ELT", "package_quantity" => "1", "price" => "40.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500452", "name" => "AHEAD WITH ENGLISH 8 VOCABULARY BOOK", "manufacturer" => "TEAM ELT", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500453", "name" => "AHEAD WITH ENGLISH 8 TEST BOOK", "manufacturer" => "TEAM ELT", "package_quantity" => "1", "price" => "30.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500454", "name" => "PASS İNGİLİZCE LGS DENEMELERİ 8", "manufacturer" => "TEAM ELT", "package_quantity" => "1", "price" => "29.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500455", "name" => "THE CHASE TESTING GUIDE 8", "manufacturer" => "UNIVERSAL ELT", "package_quantity" => "1", "price" => "45.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500456", "name" => "THE CHASE 8 PRACTICE TESTS (MASTERPRIECE)", "manufacturer" => "UNIVERSAL ELT", "package_quantity" => "1", "price" => "42.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>,İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500457", "name" => "THE CHASE 8 WORKSHEETS", "manufacturer" => "UNIVERSAL ELT", "package_quantity" => "1", "price" => "42.00", "tax" => 0, "class" => "8.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500458", "name" => "LOGİSCH NE A2 .1  KURSBUCH + ARBEITSBUCH ''GEÇEN YILDAN DEVAM''", "manufacturer" => "KLETT", "package_quantity" => "1", "price" => "206.00", "tax" => 0, "class" => "8.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500459", "name" => "SEFİLLER ", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500460", "name" => "BUZLAR ÇÖZÜLMEDEN", "manufacturer" => "İNKLAP", "package_quantity" => "1", "price" => "20.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500461", "name" => "BENİM ATATÜRK'ÜM", "manufacturer" => "KIRMIZI KEDİ", "package_quantity" => "1", "price" => "22.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500462", "name" => "ÇALIKUŞU", "manufacturer" => "İNKLAP", "package_quantity" => "1", "price" => "56.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500463", "name" => "BEYAZ DİŞ", "manufacturer" => "BEYAZ BALİNA", "package_quantity" => "1", "price" => "13.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500464", "name" => "SİMYACI", "manufacturer" => "CAN YAY.", "package_quantity" => "1", "price" => "26.50", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500465", "name" => "KÜRK MANTOLU MADONNA", "manufacturer" => "YKY", "package_quantity" => "1", "price" => "7.50", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500466", "name" => "YABAN", "manufacturer" => "İLETİŞİM", "package_quantity" => "1", "price" => "25.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500467", "name" => "HALİME KAPTAN", "manufacturer" => "KIRMIZI KEDİ", "package_quantity" => "1", "price" => "18.00", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - ZORUNLU", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>ZORUNLU", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500468", "name" => "Ölü Ozanlar Derneği", "manufacturer" => "BİLGE KÜLTÜR SANAT", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500469", "name" => "İlköğretim Öğrencileri İçin Nutuk (Söylev)", "manufacturer" => "BİLGİ YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500470", "name" => "Ateşten Gömlek (Halide Edip Adıvar)", "manufacturer" => "CAN YAYINLARI", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA ,KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500471", "name" => "Seçme Hikayeler (Sait Faik Abasıyanık)", "manufacturer" => "YKY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500472", "name" => "Dostlar Beni Hatırlasın", "manufacturer" => "İNKLAP YAY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500473", "name" => "TOMEK Tersine Akan Nehir-1", "manufacturer" => "CAN ÇOCUK", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500474", "name" => "Orhan Veli Bütün Şiirleri (Orhan Veli Kanık)", "manufacturer" => "YKY", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP,>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500475", "name" => "Denemeler (Montaigne)", "manufacturer" => "İŞ BANKASI", "package_quantity" => "1", "price" => "", "tax" => 0, "class" => "8.SINIF", "package" => "TÜRKÇE OKUMA KİTAP - SEÇMELİ", "categories" => "KİTAPLAR>TÜRKÇE OKUMA KİTAP>SEÇMELİ", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500124", "name" => "ATA TÜRKÇE SÖZLÜK", "manufacturer" => "", "package_quantity" => "1", "price" => "16.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500163", "name" => "ATA YAZIM KLAVUZU", "manufacturer" => "", "package_quantity" => "1", "price" => "12.50", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500331", "name" => "ATA DEYİMLER VE ATASÖZLERİ SÖZLÜĞÜ", "manufacturer" => "", "package_quantity" => "1", "price" => "20.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500332", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (TÜRKÇE)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500480", "name" => "100 YAPRAK SPR. PP. KAP. DEFTER KARELİ (MATEMATİK", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500334", "name" => "ÇİZİM TAKIMI", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500482", "name" => "100 YAPRAK SPR.PP.KAP.DEFTER (KARELİ YADA ÇİZ) İNKİLAP TAR.", "manufacturer" => "", "package_quantity" => "1", "price" => "17.50", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500336", "name" => "ATA TARİH ATLASI", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500484", "name" => "120 YAPRAK SPR.PP.KAP.DEFTER (KARELİ) FEN BİLİMLERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "19.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500339", "name" => "RENKLİ KALEMLER 12 RENK ADEL", "manufacturer" => "", "package_quantity" => "1", "price" => "13.50", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500340", "name" => "ALPİNO STİCK YAPIŞTIRICI 22gr", "manufacturer" => "", "package_quantity" => "1", "price" => "8.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500129", "name" => "KÜT UÇLU ÖĞRENCİ MAKASI", "manufacturer" => "", "package_quantity" => "1", "price" => "4.50", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500342", "name" => "80 YAPRAK ÇİZGİLİ DEFTER (DİN KÜLTÜRÜ)", "manufacturer" => "", "package_quantity" => "1", "price" => "15.00", "tax" => 8, "class" => "8.SINIF", "package" => "DEFTER, ARAÇ - GEREÇ İHTİYAÇ MALZEMESİ", "categories" => "KIRTASİYE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500489", "name" => "Focus  1 Students’ Book", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "332.80", "tax" => 0, "class" => "9.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500490", "name" => "Focus  1 Workbook", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "96.30", "tax" => 0, "class" => "9.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500491", "name" => "VERSANT TEST  (2 adet)", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "333.50", "tax" => 0, "class" => "9.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500492", "name" => "PEARSON ENGLISH INTERACTIVE     (ÖZEL FİYAT)", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "224.30", "tax" => 0, "class" => "9.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>,İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500493", "name" => "TRUE STORIES ", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "174.40", "tax" => 0, "class" => "9.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500494", "name" => "Deutsch echt einfach A1.1 KB & ÜB+Audios+Videos", "manufacturer" => "KLETT", "package_quantity" => "1", "price" => "135.90", "tax" => 0, "class" => "9.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>,ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500495", "name" => "TALENS 35x50 200gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "52.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500496", "name" => "BRUYUNZELL 6'LI KALEM SETİ METAL KUTU", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500497", "name" => "SİLGİ FABER CASTELL", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500498", "name" => "KALEMTRAŞ", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500499", "name" => "TAHTA KALEMİ SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500500", "name" => "ASETAT KALEMİ S VE M", "manufacturer" => "", "package_quantity" => "2", "price" => "10.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500501", "name" => "TALENS AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "60.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500502", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "9.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500505", "name" => "Focus 2 Students’ Book", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "332.80", "tax" => 0, "class" => "10.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500506", "name" => "Focus 2 Workbook", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "96.30", "tax" => 0, "class" => "10.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500507", "name" => "VERSANT TEST  (2 adet) ", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "333.50", "tax" => 0, "class" => "10.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500508", "name" => "TRUE STORIES 3", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "174.40", "tax" => 0, "class" => "10.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500509", "name" => "PEARSON ENGLISH INTERACTIVE     (ÖZEL FİYAT)", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "224.30", "tax" => 0, "class" => "10.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>,İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500510", "name" => "Deutsch echt einfach A1.2 KB & ÜB+Audios+Videos", "manufacturer" => "KLETT", "package_quantity" => "", "price" => "135.90", "tax" => 0, "class" => "10.SINIF", "package" => "ALMANCA", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>,ALMANCA", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500495", "name" => "TALENS 35x50 200gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "52.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500496", "name" => "BRUYUNZELL 6'LI KALEM SETİ METAL KUTU", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500497", "name" => "SİLGİ FABER CASTELL", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500498", "name" => "KALEMTRAŞ", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500499", "name" => "TAHTA KALEMİ SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500500", "name" => "ASETAT KALEMİ S VE M", "manufacturer" => "", "package_quantity" => "2", "price" => "10.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500501", "name" => "TALENS AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "60.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500502", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "10.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500521", "name" => "Expert PTE Academic B1 Coursebook and MyEnglishLab/ yada b2", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "353.00", "tax" => 0, "class" => "11.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI ,DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500522", "name" => "PEARSON ENGLISH INTERACTIVE     (ÖZEL FİYAT)", "manufacturer" => "Pearson", "package_quantity" => "1", "price" => "224.30", "tax" => 0, "class" => "11.SINIF", "package" => "İNGİLİZCE", "categories" => "KİTAPLAR>YABANCI DİL KİTAPLARI>İNGİLİZCE", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500495", "name" => "TALENS 35x50 200gr RESİM DEFTERİ", "manufacturer" => "", "package_quantity" => "1", "price" => "52.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500496", "name" => "BRUYUNZELL 6'LI KALEM SETİ METAL KUTU", "manufacturer" => "", "package_quantity" => "1", "price" => "35.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500497", "name" => "SİLGİ FABER CASTELL", "manufacturer" => "", "package_quantity" => "1", "price" => "3.50", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500498", "name" => "KALEMTRAŞ", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500499", "name" => "TAHTA KALEMİ SİYAH", "manufacturer" => "", "package_quantity" => "1", "price" => "5.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500500", "name" => "ASETAT KALEMİ S VE M", "manufacturer" => "", "package_quantity" => "2", "price" => "10.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500501", "name" => "TALENS AKRİLİK BOYA 12'Lİ", "manufacturer" => "", "package_quantity" => "1", "price" => "60.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500502", "name" => "TALENS KISA SAPLI SMALL 3 LÜ FIRÇA SETİ", "manufacturer" => "", "package_quantity" => "1", "price" => "22.50", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500106", "name" => "ALPİNO 12 RENK SULU BOYA ", "manufacturer" => "", "package_quantity" => "1", "price" => "25.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
            array("code" => "500104", "name" => "PANDA 24 RENK YAĞLI PASTEL", "manufacturer" => "", "package_quantity" => "1", "price" => "50.00", "tax" => 8, "class" => "11.SINIF", "package" => "GÖRSEL SANATLAR SETİ", "categories" => "GÖRSEL SANATLAR", "store" => "SINAV KOLEJI MERKEZ", "store_id" => 6),
        );

        foreach ($data as $product) {
            $this->load->model('catalog/product');
            $this->load->model('tool/backup');
            $language_id = 1;
            $package_name = $product["store"] . " - " . $product["class"] . " - " . $product["package"];
            $package_id = $this->model_tool_backup->createPackage($package_name, [0, 6]);

            $class = $this->model_tool_backup->getClass($product["store"] . " - " . $product["class"]);

            $description = "";
            $product_model = $product["code"];
            $quantity = 10000;

            $product_data = $this->getProductByModel($product_model);

            if (!$product_data) {
                $product_data = array();
                $i_data = array(
                    'model' => (string)$product_model,
                    'sku' => (string)$product_model,
                    'upc' => (string)$product_model,
                    'ean' => (string)$product_model,
                    'jan' => (string)$product_model,
                    'isbn' => (string)$product_model,
                    'mpn' => (string)$product_model,
                    #'image' => $image_path,
                    'tag' => '',
                    'location' => '',
                    'quantity' => is_null($quantity) ? 1000 : $quantity,
                    'minimum' => 1,
                    'subtract' => 1,
                    'stock_status_id' => 7,
                    'date_available' => date('Y-m-d H:i:s'),
                    'manufacturer_id' => $this->model_tool_backup->getManufacturer($product["manufacturer"], $this->model_tool_backup->getStores(null)),
                    'shipping' => 1,
                    'price' => $product["price"],
                    'points' => 0,
                    'weight' => 0,
                    'weight_class_id' => 1,
                    'length' => 0,
                    'width' => 0,
                    'height' => 0,
                    'length_class_id' => 1,
                    'status' => $status,
                    'tax_class_id' => $this->model_tool_backup->getTaxClass($product["tax"]),
                    'sort_order' => 1,
                    'product_description' => array(
                        $language_id => array(
                            'name' => $product["name"],
                            'tag' => '',
                            'description' => $description,
                            'package_description' => $description,
                            'meta_title' => $name,
                            'meta_description' => $name,
                            'meta_keyword' => $name,
                        )
                    ),
                    'product_category' => $this->model_tool_backup->getCategories($product["categories"]),
                    'product_store' => array(0, 6),
                    'product_image' => array()
                );

                $product_data["product_id"] = $this->model_catalog_product->addProduct($i_data);
            }

            var_dump($product_data);

            $this->model_tool_backup->addProductToPackage($package_id, $product_data["product_id"], $product["package_quantity"]);
            $this->model_tool_backup->addPackageToClass($class, $package_id);

            $this->model_tool_backup->productsToStore();
        }
    }

    public function getProductByModel($model) {
        $query = $this->db->query("SELECT * FROM product WHERE model = '" . $this->db->escape($model) . "' LIMIT 1");
        return $query->row;
    }
}