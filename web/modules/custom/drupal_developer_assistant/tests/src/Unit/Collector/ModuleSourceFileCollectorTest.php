<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Collector;

use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollector;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests safe module source-file discovery without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class ModuleSourceFileCollectorTest extends UnitTestCase {

  /**
   * Temporary application root used by each test.
   */
  private string $testRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->testRoot = sys_get_temp_dir()
      . '/drupal-developer-assistant-source-files-'
      . bin2hex(random_bytes(6));
    mkdir($this->testRoot . '/modules/custom/example', 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->removeDirectory($this->testRoot);
    parent::tearDown();
  }

  /**
   * Tests file classification, sorting, exclusions, and path boundaries.
   */
  public function testCollectsSafeModuleFileMetadata(): void {
    $files = [
      'README.md' => "# Example\n",
      'config/install/example.settings.yml' => "enabled: true\n",
      'css/admin.css' => "body {}\n",
      'data.txt' => "data\n",
      'example.module' => "<?php\n",
      'js/admin.js' => "alert(1);\n",
      'src/Example.php' => "<?php\n",
      'templates/example.html.twig' => "Hello\n",
      'tests/src/ExampleTest.php' => "<?php\n",
      '.git/config' => "ignored\n",
      'node_modules/package/index.js' => "ignored\n",
      'vendor/package/File.php' => "<?php\n",
    ];
    foreach ($files as $relative_path => $contents) {
      $this->writeFile(
        'modules/custom/example/' . $relative_path,
        $contents,
      );
    }
    $this->writeFile('outside.txt', "outside\n");
    symlink(
      $this->testRoot . '/outside.txt',
      $this->testRoot . '/modules/custom/example/outside-link.txt',
    );

    $module = new ModuleComponent(
      id: 'example',
      label: 'Example',
      sourcePath: 'modules/custom/example',
      package: NULL,
      version: NULL,
      dependencies: [],
    );
    $collector = new ModuleSourceFileCollector($this->testRoot);
    $records = $collector->collect($module);

    $this->assertSame([
      ['README.md', 'Markdown', 'Documentation'],
      ['config/install/example.settings.yml', 'YAML', 'Configuration'],
      ['css/admin.css', 'CSS', 'Asset'],
      ['data.txt', 'Other', 'Other'],
      ['example.module', 'PHP', 'Procedural'],
      ['js/admin.js', 'JavaScript', 'Asset'],
      ['src/Example.php', 'PHP', 'Source code'],
      ['templates/example.html.twig', 'Twig', 'Template'],
      ['tests/src/ExampleTest.php', 'PHP', 'Test'],
    ], array_map(
      static fn($record): array => [
        $record->relativePath,
        $record->fileType,
        $record->category,
      ],
      $records,
    ));
    $this->assertSame(
      'modules/custom/example/README.md',
      $records[0]->sourcePath,
    );
    $this->assertSame(strlen("# Example\n"), $records[0]->size);

    $outside_module = new ModuleComponent(
      id: 'outside',
      label: 'Outside',
      sourcePath: '..',
      package: NULL,
      version: NULL,
      dependencies: [],
    );
    $this->assertSame([], $collector->collect($outside_module));
  }

  /**
   * Writes a fixture file below the test's temporary root.
   */
  private function writeFile(string $relative_path, string $contents): void {
    $path = $this->testRoot . '/' . $relative_path;
    $directory = dirname($path);
    if (!is_dir($directory)) {
      mkdir($directory, 0777, TRUE);
    }
    file_put_contents($path, $contents);
  }

  /**
   * Removes the explicit temporary fixture directory after a test.
   */
  private function removeDirectory(string $directory): void {
    if (!is_dir($directory)) {
      return;
    }

    $files = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator(
        $directory,
        \FilesystemIterator::SKIP_DOTS,
      ),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
      if ($file->isLink() || $file->isFile()) {
        unlink($file->getPathname());
      }
      else {
        rmdir($file->getPathname());
      }
    }
    rmdir($directory);
  }

}
