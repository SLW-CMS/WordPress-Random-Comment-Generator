<?php
/*
Plugin Name: Random Comment Generator
Description: Yazılara rastgele yorum ekleyen bir eklenti.
Version: 1.7
Author: Ali Çömez
Author URI: https://rootali.net/
*/

class RandomCommentGenerator {
    
    private $names = [];
    private $emails = [];
    private $comments = [];
    private $email_domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'mynet.com', 'yandex.com', 'yaani.com', 'icloud.com'];
    private $posts_per_page = 10;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'load_bootstrap']);
        $this->load_data();
    }

    // Bootstrap ve CSS yükleme
    public function load_bootstrap() {
        wp_enqueue_style('bootstrap-css', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        wp_enqueue_style('bootstrap-icons', 'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.5.0/font/bootstrap-icons.min.css');
        wp_enqueue_script('bootstrap-js', 'https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js', array('jquery'), '', true);
    }

    // JSON dosyalarını yükleme
    private function load_data() {
        $this->names = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'isim.json'), true);
        $this->emails = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'mail.json'), true);
        $this->comments = json_decode(file_get_contents(plugin_dir_path(__FILE__) . 'yorum.json'), true);
    }

    // Admin panel menü ekleme
    public function add_admin_menu() {
        add_menu_page(
            'Random Comment Generator',
            'Comment Generator',
            'manage_options',
            'random-comment-generator',
            [$this, 'settings_page'],
            'dashicons-randomize'
        );
    }

    // Bilgi kutusu ve tanıtım
    public function show_info_box() {
        echo '<div class="alert alert-info" role="alert" style="padding15px; margin:10px;" align="center">
                <h4 class="alert-heading">Random Comment Generator’a Hoş Geldiniz!</h4>
                <p>Bu eklenti sayesinde yazılarınıza rastgele yorumlar ekleyebilir, yorum sayısını ve tarihini belirleyebilirsiniz.</p>
                <hr>
                <p class="mb-0">Önde ve şikayetleriniz için: sys@rootali.net</p>
              </div>';
    }

    // Admin panelde ayar sayfası
    public function settings_page() {
        if (isset($_POST['confirm_comments'])) {
            $this->confirm_comments_page();
        } elseif (isset($_POST['submit_comments'])) {
            $this->process_comments();
        } else {
            $this->show_info_box();
            $this->show_posts_table();
        }
    }

    // Veritabanından yazıları çekip tablo olarak gösterme (Çoklu Seçim) + Arama + Kategori + Sayfalama
    public function show_posts_table() {
        $post_limit = isset($_POST['post_limit']) ? intval($_POST['post_limit']) : $this->posts_per_page;
        $search_term = isset($_POST['search_term']) ? sanitize_text_field($_POST['search_term']) : '';
        $selected_category = isset($_POST['selected_category']) ? intval($_POST['selected_category']) : 0;
        $paged = isset($_POST['paged']) ? max(1, intval($_POST['paged'])) : 1;

        $query_args = [
            'posts_per_page' => $post_limit,
            's' => $search_term,
            'category' => $selected_category,
            'paged' => $paged,
        ];
        $posts = get_posts($query_args);
        $total_posts = wp_count_posts()->publish;

        echo '<div class="wrap"><h1 class="mb-4">Yorum Yapılacak Yazıları Seçin</h1>';
        
        // Arama ve filtre seçenekleri
        echo '<form method="post" action="" class="form-inline mb-3">';
        echo '<label class="mr-2">Gösterilecek Yazı Sayısı:</label>';
        echo '<input type="number" name="post_limit" value="' . esc_attr($post_limit) . '" min="1" style="width: 70px;" class="form-control mr-2">';

        echo '<label class="mr-2">Yazı Ara:</label>';
        echo '<input type="text" name="search_term" value="' . esc_attr($search_term) . '" class="form-control mr-2" placeholder="Yazı başlığı...">';

        echo '<label class="mr-2">Kategori Filtrele:</label>';
        wp_dropdown_categories([
            'show_option_all' => 'Tümü',
            'name' => 'selected_category',
            'selected' => $selected_category,
            'hide_empty' => 0,
            'value_field' => 'term_id',
            'class' => 'form-control'
        ]);

        echo '<button type="submit" class="btn btn-primary ml-2"><i class="bi bi-search"></i> Filtrele</button>';
        echo '</form>';

        // Yazıların listesi
        echo '<form method="post" action="">';
        echo '<table class="table table-striped table-hover">';
        echo '<thead class="thead-dark"><tr><th style="width: 5%;">Seç</th><th>Başlık</th><th>Kategori</th><th>Yorum Sayısı</th><th>Tarih</th></tr></thead><tbody>';

        foreach ($posts as $post) {
            $categories = get_the_category($post->ID);
            $category_names = array_map(function($cat) { return $cat->name; }, $categories);
            $category_display = implode(", ", $category_names);
            $comments_count = get_comments_number($post->ID);

            echo '<tr>';
            echo '<td><input type="checkbox" name="post_ids[]" value="' . $post->ID . '"></td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($category_display) . '</td>';
            echo '<td>' . esc_html($comments_count) . '</td>';
            echo '<td>' . esc_html($post->post_date) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<label for="comment_count" class="d-block mt-3">Yorum Sayısı (Her yazıya aynı sayıda):</label>';
        echo '<input type="number" name="comment_count" min="1" max="10" value="1" class="form-control mb-3">';

        // Sayfalama Navigasyonu
        $total_pages = ceil($total_posts / $post_limit);
        echo '<div class="d-flex justify-content-center mb-4">';
        echo '<nav><ul class="pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            echo '<li class="page-item' . ($paged == $i ? ' active' : '') . '">';
            echo '<button type="submit" name="paged" value="' . $i . '" class="page-link">' . $i . '</button>';
            echo '</li>';
        }
        echo '</ul></nav>';
        echo '</div>';

        echo '<button type="submit" name="confirm_comments" class="btn btn-success btn-block"><i class="bi bi-arrow-right-circle"></i> Sonraki Adım</button>';
        echo '</form></div>';
    }

    // Yorumları kontrol etme sayfası ve elle düzenleme imkanı
    public function confirm_comments_page() {
        $post_ids = $_POST['post_ids'];
        $comment_count = intval($_POST['comment_count']);

        echo '<div class="wrap"><h1 class="mb-4">Yorumları Kontrol Et ve Düzenle</h1>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="comment_count" value="' . esc_attr($comment_count) . '">';
        
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            $post_date = $post->post_date;

            echo '<h2>' . esc_html($post->post_title) . ' için yorumlar:</h2>';
            echo '<input type="hidden" name="post_ids[]" value="' . esc_attr($post_id) . '">';
            
            for ($i = 0; $i < $comment_count; $i++) {
                $name = $this->names[array_rand($this->names)];
                $domain = $this->email_domains[array_rand($this->email_domains)];
                $email = strtolower(str_replace([' ', 'ç', 'ğ', 'ö', 'ş', 'ü', 'ı'], '', $name)) . '@' . $domain;
                $comment_content = $this->comments[array_rand($this->comments)];

                echo '<div class="comment-preview alert alert-secondary mt-3">';
                echo '<div class="form-group">';
                echo '<label>İsim:</label>';
                echo '<input type="text" name="names[' . $post_id . '][]" value="' . esc_attr($name) . '" class="form-control">';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>Email:</label>';
                echo '<input type="text" name="emails[' . $post_id . '][]" value="' . esc_attr($email) . '" class="form-control">';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>Yorum:</label>';
                echo '<textarea name="comments[' . $post_id . '][]" class="form-control" rows="3">' . esc_textarea($comment_content) . '</textarea>';
                echo '</div>';
                echo '<div class="form-group">';
                echo '<label>Yorum Tarihi (Yazı Tarihinden Sonra):</label>';
                echo '<input type="date" name="dates[' . $post_id . '][]" min="' . date('Y-m-d', strtotime($post_date)) . '" value="' . date('Y-m-d') . '" class="form-control">';
                echo '</div>';
                echo '</div>';
            }
        }

        echo '<button type="submit" name="submit_comments" class="btn btn-primary btn-block"><i class="bi bi-send"></i> Yorumları Gönder</button>';
        echo '</form></div>';
    }

    // Yorumları işleme ve gönderme
    public function process_comments() {
        $post_ids = $_POST['post_ids'];
        $names = $_POST['names'];
        $emails = $_POST['emails'];
        $comments = $_POST['comments'];
        $dates = $_POST['dates'];

        foreach ($post_ids as $post_id) {
            $comment_count = count($names[$post_id]);

            for ($i = 0; $i < $comment_count; $i++) {
                $comment_date = date('Y-m-d H:i:s', strtotime($dates[$post_id][$i] . ' ' . rand(1, 23) . ':' . rand(0, 59) . ':' . rand(0, 59)));
                
                $comment_data = [
                    'comment_post_ID' => $post_id,
                    'comment_author' => sanitize_text_field($names[$post_id][$i]),
                    'comment_author_email' => sanitize_email($emails[$post_id][$i]),
                    'comment_content' => sanitize_textarea_field($comments[$post_id][$i]),
                    'comment_approved' => 0,
                    'comment_date' => $comment_date
                ];

                wp_insert_comment($comment_data);
            }
        }

        echo '<div class="updated"><p>Yorumlar başarıyla eklendi ve onay bekliyor!</p></div>';
    }
}

// Eklentiyi başlat
new RandomCommentGenerator();
