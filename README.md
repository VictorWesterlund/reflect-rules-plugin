# Request validation plugin for the [Reflect API Framework](https://github.com/victorwesterlund/reflect)
This request pre-processor adds request validation to an API written for the Reflect API Framework. Enforce request constraints against set rules and optionally return errors back with a `Reflect\Response` before your endpoint's code even starts running.

Write Reflect endpoints safer by assuming data is what you expect it to be before it reaches your endpoint's logic. This plugin will validate GET and POST parameters against user-defined constraints before letting a request through to a `Reflect\Endpoint`.
A `Reflect\Response` will be generated and handled by this plugin if request data doesn't meet the defiend constraints.

*Example:*
```
Request: /my-endpoint?key1=lorem-ipsum&key2=dolor
Response: (HTTP 422) {"key2": ["Value must be of type 'STRING']}
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
        ->type(Type::NUMBER)
        ->max(255)
    ]);
  }

  public function main(): Response {
    return new Response("Request is valid!");
  }
}
```

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

# Available rules
The following methods can be chained onto a `Rules` instance to enforce certain constraints on a particular property

## `required()`
```php
Rules->required(bool = true);
```

Make a property mandatory by chaining the `required()` method. Omitting this rule will only validate other rules on the property IF the key has been provided in the current scope

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
`Type::BOOLEAN`|Value must be a boolean or ([**considered bool for GET rules**](#boolean-coercion-from-string-for-search-parameters))
`Type::ARRAY`|Value must be a JSON array
`Type::OBJECT`|Value must be a JSON object
`Type::NULL`|Value must be null or ([**considered null for GET rules**](#null-coercion-from-string-for-search-parameters))

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
