<?php

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Component\Rectangle;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\image_effects\Component\Rectangle
 * @group image_effects
 */
class RectangleTest extends TestCase {

  /**
   * Tests wrong rectangle width.
   *
   * @covers ::rotate
   */
  public function testWrongWidth(): void {
    $this->expectException(\InvalidArgumentException::class);
    new Rectangle(-40, 20);
  }

  /**
   * Tests wrong rectangle height.
   *
   * @covers ::rotate
   */
  public function testWrongHeight(): void {
    $this->expectException(\InvalidArgumentException::class);
    new Rectangle(40, 0);
  }

  /**
   * Tests getting rectangle dimensions after a rotation operation.
   *
   * @param int $width
   *   The width of the rectangle.
   * @param int $height
   *   The height of the rectangle.
   * @param float $angle
   *   The angle for rotation.
   * @param int $exp_width
   *   The expected width of the rotated rectangle.
   * @param int $exp_height
   *   The expected height of the rotated rectangle.
   *
   * @covers ::rotate
   * @covers ::getBoundingWidth
   * @covers ::getBoundingHeight
   *
   * @dataProvider providerGd222RotateDimensions
   */
  public function testRotateDimensions(int $width, int $height, float $angle, int $exp_width, int $exp_height): void {
    $rect = new Rectangle($width, $height);
    $rect->rotate($angle);
    $this->assertEquals($exp_width, $rect->getBoundingWidth());
    $this->assertEquals($exp_height, $rect->getBoundingHeight());
  }

  /**
   * Tests matching of the sample data against GD.
   *
   * @param int $width
   *   The width of the rectangle.
   * @param int $height
   *   The height of the rectangle.
   * @param float $angle
   *   The angle for rotation.
   * @param int $exp_width
   *   The expected width of the rotated rectangle.
   * @param int $exp_height
   *   The expected height of the rotated rectangle.
   *
   * @requires gd
   *
   * @dataProvider providerGd222RotateDimensions
   */
  public function testGdRotate(int $width, int $height, float $angle, int $exp_width, int $exp_height): void {
    $libgd_version_from_info = gd_info()['GD Version'];
    $libgd_version_found = preg_match('/.*((0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*))/', $libgd_version_from_info, $matches);
    if ($libgd_version_found !== 1) {
      $this->markTestSkipped('Cannot determine the GD version available.');
    }
    if (version_compare($matches[1], '2.2.2', '<') || version_compare($matches[1], '2.3.0', '>=')) {
      $this->markTestSkipped("GD version above 2.2.2 and below 2.3.0 is required, {$matches[1]} found.");
    }

    $image = imagecreatetruecolor($width, $height);
    $rotated_image = imagerotate($image, $angle, 0);
    $this->assertSame($exp_width, imagesx($rotated_image));
    $this->assertSame($exp_height, imagesy($rotated_image));
    imagedestroy($rotated_image);
    imagedestroy($image);
  }

  /**
   * Provides data for image dimension rotation tests.
   *
   * This dataset sample was generated by running, on PHP 7.0.25 compiled with
   * libgd 2.2.2, the function below:
   * - first, for all integer rotation angles (-360 to 360) on a rectangle
   *   40x20;
   * - second, for 500 random float rotation angle in the range -360 to 360 on
   *   a rectangle 40x20;
   * - third, on 1000 rectangles of random WxH rotated to a random float angle
   *   in the range -360 to 360
   * - fourth, on 2000 rectangles of random WxH rotated to a random integer
   *   angle multiple of 30 degrees in the range -360 to 360 (which is the most
   *   tricky case).
   * Using the GD library functions gives us the ground truth data coming from
   * the GD library that can be used to match against the Rectangle class under
   * test.
   * @code
   *   protected function rotateResults($width, $height, $angle, &$new_width, &$new_height) {
   *     $image = imagecreatetruecolor($width, $height);
   *     $rotated_image = imagerotate($image, $angle, 0);
   *     $new_width = imagesx($rotated_image);
   *     $new_height = imagesy($rotated_image);
   *     imagedestroy($rotated_image);
   *     imagedestroy($image);
   *   }
   * @endcode
   *
   * @return array[]
   *   A simple array of simple arrays, each having the following elements:
   *   - original image width
   *   - original image height
   *   - rotation angle in degrees
   *   - expected image width after rotation
   *   - expected image height after rotation
   */
  public function providerGd222RotateDimensions(): array {
    // The dataset is stored in a .json file because it is very large and causes
    // problems for PHPCS.
    return json_decode(file_get_contents(__DIR__ . '/../../fixtures/RectangleTest.json'));
  }

}
