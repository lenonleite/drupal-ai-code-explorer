<?php

declare(strict_types=1);

namespace Drupal\Tests\drupal_developer_assistant\Unit\Analyzer;

use Drupal\drupal_developer_assistant\Analyzer\PhpSourceAnalyzer;
use Drupal\drupal_developer_assistant\Collector\ModuleSourceFileCollector;
use Drupal\drupal_developer_assistant\Model\ModuleComponent;
use Drupal\drupal_developer_assistant\Model\ModuleSourceFileComponent;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests static PHP structure analysis without booting Drupal.
 */
#[Group('drupal_developer_assistant')]
final class PhpSourceAnalyzerTest extends UnitTestCase {

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
      . '/drupal-developer-assistant-php-analysis-'
      . bin2hex(random_bytes(6));
    mkdir($this->testRoot . '/modules/custom/example/src', 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->removeDirectory($this->testRoot);
    parent::tearDown();
  }

  /**
   * Tests names, imports, symbols, signatures, and secret-value omission.
   */
  public function testAnalyzesPhpStructureWithoutExecutingCode(): void {
    $code = <<<'PHP'
<?php

namespace Drupal\example;

use Drupal\Core\{
  Config\ConfigFactoryInterface as ConfigFactory,
  StringTranslation\StringTranslationTrait,
};
use PHPUnit\Framework\Attributes\Group;
use function strlen as string_length;
use const PHP_VERSION as VERSION;

#[Group('example')]
final readonly class ExampleService extends BaseService implements ExampleInterface {

  use StringTranslationTrait;

  #[\Sensitive]
  public string $label;

  public function __construct(
    private readonly ConfigFactory $configFactory,
    #[\SensitiveParameter] string $secret = 'hidden-secret-value',
  ) {}

  #[\Deprecated]
  protected static function &build(
    ?ConfigFactory $factory,
    string|int ...$ids,
  ): array {
    return [];
  }

}

interface ExampleInterface extends ParentInterface {}

trait HelperTrait {}

enum Status: string implements \JsonSerializable {

  case Active = 'active';

  public function jsonSerialize(): mixed {
    return $this->value;
  }

}

function example_hook(
  ConfigFactory $factory,
  string $secret = 'another-hidden-value',
): void {
  string_length($secret);
}
PHP;
    $this->writeFile('modules/custom/example/src/Example.php', $code);

    $module = $this->module();
    $files = (new ModuleSourceFileCollector($this->testRoot))->collect(
      $module,
    );
    $this->assertCount(1, $files);

    $analyzer = new PhpSourceAnalyzer($this->testRoot);
    $analysis = $analyzer->analyze($module, $files[0]);

    $this->assertNull($analysis->error);
    $this->assertSame(['Drupal\example'], $analysis->namespaces);
    $this->assertSame([
      'ConfigFactory' => 'Drupal\Core\Config\ConfigFactoryInterface',
      'Group' => 'PHPUnit\Framework\Attributes\Group',
      'StringTranslationTrait' => 'Drupal\Core\StringTranslation\StringTranslationTrait',
      'const VERSION' => 'PHP_VERSION',
      'function string_length' => 'strlen',
    ], $analysis->imports);
    $this->assertSame(
      ['class', 'interface', 'trait', 'enum'],
      array_column($analysis->symbols, 'kind'),
    );

    $class = $analysis->symbols[0];
    $this->assertSame('ExampleService', $class->name);
    $this->assertSame(
      'Drupal\example\ExampleService',
      $class->fullyQualifiedName,
    );
    $this->assertSame(['final', 'readonly'], $class->modifiers);
    $this->assertSame(['Drupal\example\BaseService'], $class->extends);
    $this->assertSame(
      ['Drupal\example\ExampleInterface'],
      $class->implements,
    );
    $this->assertSame(
      ['Drupal\Core\StringTranslation\StringTranslationTrait'],
      $class->traits,
    );
    $this->assertSame(
      ['PHPUnit\Framework\Attributes\Group'],
      $class->attributes,
    );
    $this->assertNotNull($class->startLine);
    $this->assertNotNull($class->endLine);
    $this->assertGreaterThanOrEqual($class->startLine, $class->endLine);
    $this->assertCount(1, $class->properties);
    $this->assertSame('label', $class->properties[0]->name);
    $this->assertSame('string', $class->properties[0]->type);
    $this->assertSame(['Sensitive'], $class->properties[0]->attributes);

    $this->assertSame(
      ['__construct', 'build'],
      array_column($class->methods, 'name'),
    );
    $constructor = $class->methods[0];
    $this->assertNotNull($constructor->startLine);
    $this->assertNotNull($constructor->endLine);
    $this->assertGreaterThanOrEqual(
      $constructor->startLine,
      $constructor->endLine,
    );
    $this->assertSame(
      'Drupal\Core\Config\ConfigFactoryInterface',
      $constructor->parameters[0]->type,
    );
    $this->assertTrue($constructor->parameters[0]->promoted);
    $this->assertFalse($constructor->parameters[0]->hasDefault);
    $this->assertSame(
      ['SensitiveParameter'],
      $constructor->parameters[1]->attributes,
    );
    $this->assertTrue($constructor->parameters[1]->hasDefault);

    $build = $class->methods[1];
    $this->assertSame('protected', $build->visibility);
    $this->assertTrue($build->static);
    $this->assertTrue($build->returnsByReference);
    $this->assertSame('array', $build->returnType);
    $this->assertSame(
      '?Drupal\Core\Config\ConfigFactoryInterface',
      $build->parameters[0]->type,
    );
    $this->assertSame('string|int', $build->parameters[1]->type);
    $this->assertTrue($build->parameters[1]->variadic);
    $this->assertSame(['Deprecated'], $build->attributes);

    $this->assertCount(1, $analysis->functions);
    $this->assertSame(
      'Drupal\example\example_hook',
      $analysis->functions[0]->fullyQualifiedName,
    );
    $this->assertSame(
      'Drupal\Core\Config\ConfigFactoryInterface',
      $analysis->functions[0]->parameters[0]->type,
    );
    $this->assertNotNull($analysis->functions[0]->startLine);
    $this->assertNotNull($analysis->functions[0]->endLine);
    $this->assertGreaterThanOrEqual(
      $analysis->functions[0]->startLine,
      $analysis->functions[0]->endLine,
    );
    $serialized_analysis = serialize($analysis);
    $this->assertStringNotContainsString(
      'hidden-secret-value',
      $serialized_analysis,
    );
    $this->assertStringNotContainsString(
      'another-hidden-value',
      $serialized_analysis,
    );
  }

  /**
   * Tests controlled failures for invalid syntax, types, and paths.
   */
  public function testReturnsControlledAnalysisErrors(): void {
    $this->writeFile(
      'modules/custom/example/src/Broken.php',
      '<?php function broken(',
    );
    $this->writeFile('outside.php', '<?php function outside() {}');

    $module = $this->module();
    $files = (new ModuleSourceFileCollector($this->testRoot))->collect(
      $module,
    );
    $analyzer = new PhpSourceAnalyzer($this->testRoot);

    $broken = $analyzer->analyze($module, $files[0]);
    $this->assertNotNull($broken->error);
    $this->assertStringStartsWith('PHP parsing failed', $broken->error);

    $non_php = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: 'example.yml',
      fileType: 'YAML',
      category: 'Configuration',
      size: 0,
      sourcePath: 'modules/custom/example/example.yml',
    );
    $this->assertSame(
      'Only PHP source files can be analyzed.',
      $analyzer->analyze($module, $non_php)->error,
    );

    $outside = new ModuleSourceFileComponent(
      moduleId: 'example',
      relativePath: '../../../outside.php',
      fileType: 'PHP',
      category: 'Source code',
      size: 27,
      sourcePath: 'outside.php',
    );
    $this->assertSame(
      'The PHP source path is invalid or outside the module directory.',
      $analyzer->analyze($module, $outside)->error,
    );
  }

  /**
   * Builds the module represented by the temporary fixture tree.
   */
  private function module(): ModuleComponent {
    return new ModuleComponent(
      id: 'example',
      label: 'Example',
      sourcePath: 'modules/custom/example',
      package: NULL,
      version: NULL,
      dependencies: [],
    );
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
