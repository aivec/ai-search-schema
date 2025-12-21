<?php
/**
 * Validator tests.
 */

require_once __DIR__ . '/bootstrap.php';

class AI_Search_Schema_Validator_Test extends WP_UnitTestCase {
    public function test_article_validation_requires_fields() {
        $validator = new AI_Search_Schema_Validator();
        $result = $validator->validate([
            '@type' => 'Article',
        ]);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('Article: headline がありません', $result['errors']);
        $this->assertContains('Article: author がありません', $result['errors']);
        $this->assertContains('Article: datePublished がありません', $result['errors']);
    }

    public function test_local_business_requires_address_fields() {
        $validator = new AI_Search_Schema_Validator();
        $result = $validator->validate([
            '@type' => 'LocalBusiness',
        ]);

        $this->assertFalse($result['is_valid']);
        $this->assertContains('LocalBusiness: name がありません', $result['errors']);
        $this->assertContains('LocalBusiness: address.streetAddress がありません', $result['errors']);
    }

    public function test_local_business_passes_with_required_fields() {
        $validator = new AI_Search_Schema_Validator();
        $result = $validator->validate([
            '@type' => 'LocalBusiness',
            'name' => 'Example Store',
            'telephone' => '03-1234-5678',
            'address' => [
                'streetAddress' => '1-2-3 Test Town',
                'addressLocality' => 'Shibuya',
                'addressRegion' => 'Tokyo',
                'postalCode' => '123-4567',
                'addressCountry' => 'JP',
            ],
        ]);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_graph_validation_aggregates_errors() {
        $validator = new AI_Search_Schema_Validator();
        $result = $validator->validate([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Article',
                    'headline' => 'Valid',
                    'author' => ['@type' => 'Person', 'name' => 'Auth'],
                    'datePublished' => '2024-01-01',
                    'dateModified' => '2024-01-02',
                    'mainEntityOfPage' => 'https://example.com/article',
                    'publisher' => ['@type' => 'Organization', 'name' => 'Pub'],
                    'image' => 'https://example.com/image.jpg',
                ],
                ['@type' => 'LocalBusiness'],
            ],
        ]);

        $this->assertFalse($result['is_valid']);
        // LocalBusiness in @graph[1] should have errors
        $this->assertContains('LocalBusiness: name がありません', $result['errors']);
    }
}
