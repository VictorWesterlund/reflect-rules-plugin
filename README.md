# Request validation plugin for the [Reflect API Framework](https://github.com/victorwesterlund/reflect)
This request pre-processor adds request validation for an API written in the Reflect API Framework.

Write safer Reflect endpoints by enforcing request data structure validation before the request reaches your endpoint's logic. This plugin validates GET and POST data (even JSON) and returns an array with scoped `Error`s that can be further acted upon if desired.

*Example:*
```
GET Request: /my-endpoint?key1=lorem-ipsum&key2=dolor
POST Body: {"key3":15, "key4":["hello", "world"]}
```
```php
use \Reflect\Endpoint;
use \Reflect\Response;

use \ReflectRules\Type;
use \ReflectRules\Rules;
use \ReflectRules\Ruleset;

class GET_MyEndpoint implements Endpoint {
  private Ruleset $rules;

  public function __construct() {
    $this->rules = new Ruleset();

    $this->rules->GET([
      (new Rules("key1")
        ->required()
        ->type(Type::STRING)
        ->min(5)
        ->max(50),
      (new Rules("key2")
        ->required()
        ->type(Type::NUMBER)
        ->max(255)
    ]);

    $this->rules->POST([
      (new Rules("key3")
        ->type(Type::ARRAY)
        ->min(3),
      (new Rules("key4")
        ->required()
        ->type(Type::STRING)
        ->max(255)
    ]);
  }

  public function main(): Response {
    return new Response("Request is valid!");
  }
}
```
```php
Ruleset->get_errors();
[
  "GET" => [
    "key2" => [
      "INVALID_PROPERTY_TYPE" => ["STRING"]
    ]
  ],
  "POST" => [
    "key3" => [
      "VALUE_MIN_ERROR" => 3
    ],
    "key4" => [
      "MISSING_REQUIRED_PROPERTY" => "key4"
    ]
  ]
]
```

Use `Ruleset->is_valid(): bool` to quickly check if any errors are set.

# Installation

Install with composer
```
composer require reflect/plugin-rules
```

Include (at least) `Ruleset` and `Rules` in your endpoint file
```php
use \ReflectRules\Rules;
use \ReflectRules\Ruleset;
```

Instantiate a new `Ruleset`
```php
public function __construct() {
  $this->rules = new Ruleset();
}
```

Run a `GET` and/or `POST` validation with the `GET()` and `POST()` `Ruleset` methods anywhere before you expect data to be valid
```php
public function __construct() {
  $this->rules = new Ruleset();

  $this->rules->GET(<Rules_Array>);
}
```

# Errors 

Error
--|
[`Error::VALUE_MIN_ERROR`](#min)
[`Error::VALUE_MAX_ERROR`](#max)
[`Error::UNKNOWN_PROPERTY_NAME`](#strict-mode)
[`Error::INVALID_PROPERTY_TYPE`](#type)
[`Error::INVALID_PROPERTY_VALUE`](#typeenum)
[`Error::MISSING_REQUIRED_PROPERTY`](#required)

# Strict mode
Enable strict mode by initializing a Ruleset with the "strict" argument set to `true`.

```php
new Ruleset(strict: true);
```

Strict mode will not allow undefined properties to be set in all configured scopes. If a property exists in `Scope` that hasn't been defined with a `Rules()` instance, a `Errors::UNKNOWN_PROPERTY_NAME` error will be set.

# Available rules
The following methods can be chained onto a `Rules` instance to enforce certain constraints on a particular property

## `required()`
```php
Rules->required(bool = true);
```

Make a property mandatory by chaining the `required()` method. Omitting this rule will only validate other rules on the property IF the key has been provided in the current scope.

Will set a `Error::MISSING_REQUIRED_PROPERTY` error on the current scope and property if failed.

## `type()`
```php
Rules->type(Type);
```

Enforce a data type on the request by chaining the `type()` method and passing it one of the available enum [`Type`](#types)s as its argument.

> [!TIP]
> Allow multiple types (union) by chaining multiple `type()` methods
> ```php
> // Example
> Rules->type(Type::NUMBER)->type(Type::NULL);
> ```

### Types
Type|Description
--|--
`Type::NUMERIC`|Value must be a number or a numeric string
`Type::STRING`|Value must be a string
`Type::BOOLEAN`|Value must be a boolean ([**considered bool for GET rules**](#boolean-coercion-from-string-for-search-parameters))
`Type::ARRAY`|Value must be a JSON array or ([**CSV for GET rules**](#csv-for-search-parameters))
`Type::OBJECT`|Value must be a JSON object
`Type::ENUM`|Value must be exactly one of pre-defined values ([**more information**](#typeenum))
`Type::NULL`|Value must be null ([**considered null for GET rules**](#null-coercion-from-string-for-search-parameters))

Will set a `Error::INVALID_PROPERTY_TYPE` error on the current scope and property if failed, except Type::ENUM that will set a `Error::INVALID_PROPERTY_VALUE` with an array of the valid vaules.

#### `Type::ENUM`

Provided value for property must be an exact match of any value provided as an `array` to the second argument of `type(Type::ENUM, <whitelist>)`
```php
Rules->type(Type::ENUM, [
  "FOO",
  "BAR"
]);
```
Any value that isn't `"FOO"` or `"BAR"` will be rejected.

Will set a `Error::INVALID_PROPERTY_VALUE` error on the current scope and property if failed.

#### Boolean coercion from string for search parameters
Search parameters are read as strings, a boolean is therefor coerced from the following rules.

Value|Coerced to
--|--
`"true"`|`true`
`"1"`|`true`
`"on"`|`true`
`"yes"`|`true`
--|--
`"false"`|`false`
`"0"`|`false`
`"off"`|`false`
`"no"`|`false`

Any other value will cause the `type()` rule to fail.

> [!IMPORTANT]
> This coercion is only applies for `Ruleset->GET()`. `Ruleset->POST()` will enforce real `true` and `type` values since it's JSON

#### CSV array for search parameters
A CSV string is expected when `Type::ARRAY` is set for a GET rule.

*Example:*
```
https://example.com?typeArray=key1,key2,key3
```

Any other value will cause the `type()` rule to fail.

> [!IMPORTANT]
> This coercion is only applies for `Ruleset->GET()`. `Ruleset->POST()` will enforce a JSON array

#### Null coercion from string for search parameters
Search parameters are read as strings, a null value is therefor coerced from an empty string `""`.

Any value that isn't an empty string will cause the `type()` rule to fail.

> [!IMPORTANT]
> This coercion is only applies for `Ruleset->GET()`. `Ruleset->POST()` will enforce the real `null` value since it's JSON

## `default()`
```php
Rules->default(mixed);
```
Set superglobal property to a defined default value if the property was not provided in superglobal scope

## `min()`
```php
Rules->min(?int = null);
```
Enforce a minimum length/size/count on a propety depending on its [`type()`](#type)

Type|Expects
--|--
`Type::NUMERIC`|Number to be larger or equal to provided value
`Type::STRING`|String length to be larger or equal to provided value
`Type::ARRAY`, `Type::OBJECT`|Array size or object key count to be larger or equal to provided value

**`min()` will not have an effect on [`Type`](#types)s not provided in this list.**

Will set a `Error::VALUE_MIN_ERROR` error on the current scope and property if failed

## `max()`
```php
Rules->max(?int = null);
```
Enforce a maximum length/size/count on a propety depending on its [`type()`](#type)

Type|Expects
--|--
`Type::NUMERIC`|Number to be smaller or equal to provided value
`Type::STRING`|String length to be smaller or equal to provided value
`Type::ARRAY`, `Type::OBJECT`|Array size or object key count to be smaller or equal to provided value

**`max()` will not have an effect on [`Type`](#types)s not provided in this list.**

Will set a `Error::VALUE_MAX_ERROR` error on the current scope and property if failed
