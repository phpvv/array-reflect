# ArrayReflect

A simple PHP library to help modify and get the contents of arrays

## Installation

Package is available on [Packagist](https://packagist.org/packages/phpvv/array-reflect), you can install it using [Composer](https://getcomposer.org).

```shell
composer require phpvv/array-reflect
```

## Get array contents with type control

```php
use VV\Net\Http\Request;
use VV\Net\Http\Response;
use App\Web\BadRequestException;

class Server implements \VV\Net\Http\Server {

    public function processHttpRequest(Request $request): Response {
        // ArrayReflect::cast($_POST)
        $request->post()->setGetterTypeExceptionClass(BadRequestException::class);
        
        try {
            return $this->route($request); // /foo/bar
        } catch (BadRequestException $e) {
            return (new Response)->badRequest($e->getMessage());
        }
    } 
    .
    .
    .
}
```
```php
use VV\Utils\ArrayReflect;
use VV\Net\Http\Response;

class FooController {
    
    function barAction(ArrayReflect $post): Response {
        // throws BadRequestException if value is "bad"
        $userId = $post->int('userId');         //  ok: 1, '2', '0003', null
                                                // bad: 1.1, '2foo', array, bool, object... 
        $rating = $post->float('rating');       //  ok: 1, 2.3, '4.5', null
                                                // bad: '2foo', array, bool, object... 
        $name = $post->string('name');          //  ok: 1, 2.3, '4', 'foo', null
                                                // bad: array, bool, object...
        $isAactive = $post->bool('isAactive');  //  ok: true, false, 0, 1, '', '0', '1', null
        $interests = $post->array('interests'); //  ok: ['1', 2, 'foo']
                                                // bad: 1, 'foo', bool, object...
        //$interests = $post->arrayReflect('interests'); // same as above but $interests instanceof ArrayReflect 

        $items = $post->intIterator('items');     // [1, '2', '003']
        //$items = $post->floatIterator('items'); // [1, 2.3, '4.5']
        //$items = $post->boolIterator('items');  // [1, 2.3, '4', 'foo']
        //$items = $post->arrayIterator('items'); // [[1, 2.3, 'foo'], ['bar', true, ['sub']], ...]
        
        $votes = $post->arrayReflectIterator('votes');
        foreach ($votes as $vote) {
            $voteUserId = $vote->int('userId');
            $voteValue = $vote->int('value');
        }
        .
        .
        .
        
        return (new Response)->setContent('Ok');
    }
    .
    .
    .
}
```

## Modify array contents by reference

```php
use VV\Utils\ArrayReflect;

$sessionReflect = new ArrayReflect($_SESSION);
$storage = $sessionReflect->iref('my', 'concrete', 'path');

$storage->set('key1', 'val1');
$storage->push('val3', 'val4');
$storage->merge(['val5', 'key' => 'val']);

print_r($_SESSION);
// Array (
//   [my] => Array (
//     [concrete] => Array (
//       [path] => Array (
//         [key1] => val1
//         [0] => val3
//         [1] => val4
//         [2] => val5
//         [key] => val
//       )
//     )
//   )
// )
```
```php
$sub = &$storage->ref('key', 'sub', 'namespace');
if ($sub === null) {
    $sub = 42;
}

print_r($_SESSION);
// Array (
//   [my] => Array (
//     [concrete] => Array (
//       [path] => Array (
//         [key1] => val1
//         [0] => val3
//         [1] => val4
//         [2] => val5
//         [key] => Array (
//           [sub] => Array (
//             [namespace] => 42
//           )
//         )
//       )
//     )
//   )
// )
```
