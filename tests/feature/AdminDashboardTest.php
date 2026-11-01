<?php

use App\Libraries\AdminAuthenticator;
use App\Models\AdminModel;
use App\Models\BlockModel;
use App\Models\CategoryModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class AdminDashboardTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (int) model(AdminModel::class)->insert([
            'username'      => 'admin',
            'email'         => 'admin@example.com',
            'password_hash' => password_hash('Correct-Horse-9', PASSWORD_DEFAULT),
        ]);
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $pages = [
            '/admin',
            '/admin/blocks',
            '/admin/blocks/create',
            '/admin/blocks/1',
            '/admin/blocks/1/edit',
            '/admin/blocks/1/delete',
            '/admin/categories',
            '/admin/categories/create',
            '/admin/categories/1/edit',
            '/admin/categories/1/delete',
            '/admin/settings',
            '/admin/services',
            '/admin/services/create',
            '/admin/services/1',
            '/admin/services/1/edit',
            '/admin/services/1/delete',
        ];

        foreach ($pages as $page) {
            $result = $this->get($page);
            $result->assertRedirectTo(site_url('admin/login'));
            $result->assertSessionHas('error', 'Please sign in to continue.');
        }
    }

    public function testDashboardShowsEmptyStatistics(): void
    {
        $result = $this->asAdmin()->get('/admin');

        $result->assertOK();
        $result->assertSee('Dashboard');
        $result->assertSee('Total posts');
        $result->assertSee('Published posts');
        $result->assertSee('Draft posts');
        $result->assertSee('Total categories');
        $result->assertSee('No posts have been updated yet.');
        $result->assertSee('New post');
        $result->assertSee('All posts');
        $result->assertSee('Manage Categories');
        $this->assertMatchesRegularExpression('/data-stat="total-blocks">0</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="published-blocks">0</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="draft-blocks">0</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="total-categories">0</', $result->getBody());
    }

    public function testDashboardStatisticsUseDatabaseRows(): void
    {
        $local = (int) model(CategoryModel::class)->insert(['name' => 'Local shifting']);
        model(CategoryModel::class)->insert(['name' => 'Office <b>move</b>']);

        $blocks = model(BlockModel::class);
        $older  = (int) $blocks->insert([
            'title'       => 'Older move',
            'status'      => BlockModel::STATUS_PUBLISHED,
            'category_id' => $local,
        ]);
        $draft  = (int) $blocks->insert([
            'title'       => 'Draft crate',
            'status'      => BlockModel::STATUS_DRAFT,
            'category_id' => $local,
        ]);
        $newest = (int) $blocks->insert([
            'title'       => 'Newest route',
            'status'      => BlockModel::STATUS_PUBLISHED,
            'category_id' => $local,
        ]);
        $unsafe = (int) $blocks->insert([
            'title'       => '<script>alert(1)</script>',
            'status'      => BlockModel::STATUS_DRAFT,
            'category_id' => $local,
        ]);

        $this->db->table('blocks')->where('id', $older)->update(['updated_at' => '2020-01-01 00:00:00']);
        $this->db->table('blocks')->where('id', $unsafe)->update(['updated_at' => '2024-01-01 00:00:00']);
        $this->db->table('blocks')->where('id', $draft)->update(['updated_at' => '2025-01-01 00:00:00']);
        $this->db->table('blocks')->where('id', $newest)->update(['updated_at' => '2026-10-27 12:00:00']);

        $result = $this->asAdmin()->get('/admin');

        $result->assertOK();
        $this->assertMatchesRegularExpression('/data-stat="total-blocks">4</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="published-blocks">2</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="draft-blocks">2</', $result->getBody());
        $this->assertMatchesRegularExpression('/data-stat="total-categories">2</', $result->getBody());
        $result->assertSee('Newest route');
        $result->assertSee('Draft crate');
        $result->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
        $result->assertDontSee('<script>alert(1)</script>');
        $this->assertLessThan(
            strpos($result->getBody(), 'Older move'),
            strpos($result->getBody(), 'Newest route'),
        );

        $categories = $this->asAdmin()->get('/admin/categories');
        $categories->assertOK();
        $categories->assertSee('Office &lt;b&gt;move&lt;/b&gt;');
        $categories->assertDontSee('<b>move</b>');
    }

    public function testEmptySectionPages(): void
    {
        $blocks = $this->asAdmin()->get('/admin/blocks');
        $blocks->assertOK();
        $blocks->assertSee('No posts yet.');

        $categories = $this->asAdmin()->get('/admin/categories');
        $categories->assertOK();
        $categories->assertSee('No categories yet.');

        $create = $this->asAdmin()->get('/admin/blocks/create');
        $create->assertOK();
        $create->assertSee('Title');
        $create->assertSee('Post');
        $create->assertSee('Save draft');
        $create->assertDontSee('Block creation is not available yet.');
    }

    public function testSettingsAndAlerts(): void
    {
        $result = $this->asAdmin([
            'success' => 'Saved.',
            'error'   => 'Could not save.',
        ])->get('/admin/settings');

        $result->assertOK();
        $result->assertSee('Signed in as admin');
        $result->assertSee('Saved.');
        $result->assertSee('Could not save.');
        $result->assertDontSee('password_hash');
    }

    public function testMobileNavigationMarkup(): void
    {
        $result = $this->asAdmin()->get('/admin');
        $body   = $result->getBody();

        $result->assertOK();
        $this->assertStringContainsString('data-nav-toggle', $body);
        $this->assertStringContainsString('aria-controls="admin-nav"', $body);
        $this->assertStringContainsString('aria-expanded="false"', $body);
        $this->assertStringContainsString('>Menu<', $body);
        $this->assertStringContainsString('aria-current="page"', $body);
        $this->assertStringContainsString('data-page-loading', $body);

        $css = file_get_contents(FCPATH . 'assets/admin/admin.css');
        $this->assertIsString($css);
        $this->assertStringContainsString('@media (max-width: 767px)', $css);
        $this->assertStringContainsString('.sidebar.is-open', $css);
        $this->assertStringContainsString('@media (max-width: 1023px)', $css);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function asAdmin(array $extra = []): self
    {
        return $this->withSession(array_merge([
            AdminAuthenticator::FLAG     => true,
            AdminAuthenticator::ID       => $this->adminId,
            AdminAuthenticator::USERNAME => 'admin',
        ], $extra));
    }
}
