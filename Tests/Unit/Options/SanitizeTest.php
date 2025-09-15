<?php

declare(strict_types=1);

namespace Ran\PluginLib\Tests\Unit\Options;

use Ran\PluginLib\Options\Sanitize;
use Ran\PluginLib\Tests\Unit\PluginLibTestCase;

/**
 * @covers \Ran\PluginLib\Options\Sanitize::orderInsensitiveDeep
 * @covers \Ran\PluginLib\Options\Sanitize::orderInsensitiveShallow
 */
final class SanitizeTest extends PluginLibTestCase {
    public function test_deep_passes_through_scalars(): void {
        $this->assertSame(123, Sanitize::orderInsensitiveDeep(123));
        $this->assertSame('abc', Sanitize::orderInsensitiveDeep('abc'));
        $this->assertSame(null, Sanitize::orderInsensitiveDeep(null));
    }

    public function test_deep_converts_object_to_array_preferring_jsonserializable(): void {
        $jsonObj = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed { return ['b' => 2, 'a' => 1]; }
        };
        $out = Sanitize::orderInsensitiveDeep($jsonObj);
        $this->assertSame(['a' => 1, 'b' => 2], $out); // assoc sorted by key after deep normalize

        $plainObj = new class { public int $z = 1; public int $a = 2; };
        $out2 = Sanitize::orderInsensitiveDeep($plainObj);
        $this->assertSame(['a' => 2, 'z' => 1], $out2);
    }

    public function test_deep_normalizes_assoc_by_keys_and_recurse(): void {
        $a = ['b' => ['y' => 2, 'x' => 1], 'a' => 9];
        $b = ['a' => 9, 'b' => ['x' => 1, 'y' => 2]]; // same semantics, different order
        $na = Sanitize::orderInsensitiveDeep($a);
        $nb = Sanitize::orderInsensitiveDeep($b);
        $this->assertSame($na, $nb);
        $this->assertSame(['a' => 9, 'b' => ['x' => 1, 'y' => 2]], $na);
    }

    public function test_deep_normalizes_lists_by_element_then_sorts_stably(): void {
        $l1 = [["k" => 2], ["k" => 1]];      // list with assoc elements
        $l2 = [["k" => 1], ["k" => 2]];      // same elements different order
        $n1 = Sanitize::orderInsensitiveDeep($l1);
        $n2 = Sanitize::orderInsensitiveDeep($l2);
        $this->assertSame($n1, $n2);
        $this->assertSame([["k" => 1], ["k" => 2]], $n1);
    }

    public function test_deep_handles_nested_mixed_list_and_assoc(): void {
        $in = [
            ['b' => [2,1]],
            ['a' => [ ['y'=>2,'x'=>1], ['x'=>1,'y'=>2] ]],
        ];
        $out = Sanitize::orderInsensitiveDeep($in);
        // Expect inner lists sorted and inner maps canonicalized, outer list sorted by JSON
        $this->assertSame([
            ['a' => [ ['x'=>1,'y'=>2], ['x'=>1,'y'=>2] ]],
            ['b' => [1,2]],
        ], $out);
    }

    public function test_shallow_passes_through_scalars(): void {
        $this->assertSame(false, Sanitize::orderInsensitiveShallow(false));
        $this->assertSame('x', Sanitize::orderInsensitiveShallow('x'));
    }

    public function test_shallow_converts_object_to_array_preferring_jsonserializable(): void {
        $obj = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed { return ['b' => 1, 'a' => 2]; }
        };
        $out = Sanitize::orderInsensitiveShallow($obj);
        $this->assertSame(['a' => 2, 'b' => 1], $out);

        $plain = new class { public $k = 3; public $a = 1; };
        $out2 = Sanitize::orderInsensitiveShallow($plain);
        // Keys sorted top-level only
        $this->assertSame(['a' => 1, 'k' => 3], $out2);
    }

    public function test_shallow_sorts_top_level_assoc_only_no_recurse(): void {
        $in = ['b' => ['z'=>2,'a'=>1], 'a' => ['y'=>2,'x'=>1]];
        $out = Sanitize::orderInsensitiveShallow($in);
        // Top-level keys sorted, nested arrays untouched (original order maintained)
        $this->assertSame(['a' => ['y'=>2,'x'=>1], 'b' => ['z'=>2,'a'=>1]], $out);
    }

    public function test_shallow_stable_sort_for_top_level_list_only(): void {
        $in = [["k"=>2], ["k"=>1]];
        $out = Sanitize::orderInsensitiveShallow($in);
        $this->assertSame([["k"=>1],["k"=>2]], $out);

        // Elements are not deep-normalized in shallow mode
        $in2 = [["b"=>2,"a"=>1], ["a"=>1,"b"=>2]];
        $out2 = Sanitize::orderInsensitiveShallow($in2);
        // Sorting by JSON means as-is JSON of elements governs order; but structure stays otherwise the same
        $this->assertSame([["a"=>1,"b"=>2],["b"=>2,"a"=>1]], $out2);
    }
}
