<?php

use PHPUnit\Framework\TestCase;

class MetaBoxSaveTest extends WP_UnitTestCase {
    private int $admin_id;
    private string $nonce;

    protected function setUp(): void {
        parent::setUp();

        $this->admin_id = self::factory()->user->create(array('role' => 'administrator'));
        wp_set_current_user($this->admin_id);
        $this->nonce = wp_create_nonce('avc_ais_meta_nonce');

        AVC_AIS_TEST_Env::$current_post_id = 0;
        AVC_AIS_TEST_Env::$posts          = [];
        AVC_AIS_TEST_Env::$post_meta      = [];
        AVC_AIS_TEST_Env::$options        = [];
        AVC_AIS_TEST_Env::$thumbnails     = [];
        AVC_AIS_TEST_Env::$attachments    = [];
        AVC_AIS_TEST_Env::$transients     = [];
        AVC_AIS_TEST_Env::$autosaves      = [];
        AVC_AIS_TEST_Env::$revisions      = [];
        AVC_AIS_TEST_Env::$capabilities   = [
            'manage_options' => true,
            'edit_post'      => true,
        ];

        $_POST = [];
    }

    public function test_autosave_does_not_override_meta() {
        $post_id = self::factory()->post->create(
            [
                'post_title' => 'Sample Post',
                'post_type'  => 'post',
            ]
        );

        update_post_meta(
            $post_id,
            '_avc_ais_meta',
            [
                'page_type'          => 'Article',
                'faq_question_class' => 'question-class',
                'faq_answer_class'   => 'answer-class',
            ]
        );

        $autosave_id = wp_create_post_autosave(
            [
                'post_ID'      => $post_id,
                'post_title'   => 'Sample Post',
                'post_type'    => 'post',
                'post_content' => 'Autosave',
            ]
        );

        $_POST = [
            'avc_ais_meta_nonce' => $this->nonce,
            'avc_ais_meta'       => [
                'page_type'          => 'FAQPage',
                'faq_question_class' => 'new-question',
                'faq_answer_class'   => 'new-answer',
            ],
        ];

        $metabox = new AVC_AIS_MetaBox();
        $metabox->save_meta_box($autosave_id);

        $meta = get_post_meta($post_id, '_avc_ais_meta', true);

        $this->assertSame(
            [
                'page_type'          => 'Article',
                'faq_question_class' => 'question-class',
                'faq_answer_class'   => 'answer-class',
            ],
            $meta
        );
    }

    public function test_invalid_values_are_not_saved() {
        $post_id = self::factory()->post->create(
            [
                'post_title' => 'Another Post',
                'post_type'  => 'page',
            ]
        );

        $_POST = [
            'avc_ais_meta_nonce' => $this->nonce,
            'avc_ais_meta'       => [
                'page_type'          => 'InvalidType',
                'faq_question_class' => 'invalid class!',
                'faq_answer_class'   => '日本語',
            ],
        ];

        $metabox = new AVC_AIS_MetaBox();
        $metabox->save_meta_box($post_id);

        $meta = get_post_meta($post_id, '_avc_ais_meta', true);

        $this->assertSame(
            [
                'page_type' => 'auto',
            ],
            $meta
        );
    }
}
