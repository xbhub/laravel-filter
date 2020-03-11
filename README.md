# Clean & Easy Eloquent Filtration

* [Installation](#installation)
* [Usage](#usage)
  * [Creating Filters](#creating-filters)
  * [Mappings](#mappings)
  * [Using "individual" filters](#using-individual-filters)
  * [Filter Bags](#filter-bags)
  * [Default Filters](#default-filters)

This package allows you to filter retrieved records based on query string values.

Once installed filtration shrinks down from this:

```php
public function index(Request $request)
{
    $query = Product::query();

    if ($request->has('color')) {
        $query->where('color', $request->get('color'));
    }

    if ($request->has('condition')) {
        $query->where('condition', $request->get('condition'));
    }

    if ($request->has('price')) {
        $direction = $request->get('price') === 'highest' ? 'desc' : 'asc';
        $query->orderBy('price', $direction);
    }

    return $query->get();
}
```
to this:
```php
public function index(Request $request)
{
    return Product::filter($request)->get();
}
```

## Installation

This package can be used in Laravel 5.3 or higher.
You can install the package via composer:

``` bash
composer require xbhub/filter
```

The service provider will automatically get registered. Or you may manually add the service provider in your `config/app.php` file:

```php
'providers' => [
    // ...
    Xbhub\Filter\FiltersServiceProvider::class,
];
```
## Usage

Enabling filtration for a model is as easy as adding the `Xbhub\Filter\Concerns\Filterable` trait to your model(s):

```php
use Xbhub\Filter\Concerns\Filterable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Filterable;

    // ...
}
```
The `Filterable` trait adds a `filter` [local scope](https://laravel.com/docs/5.8/eloquent#local-scopes) to your model which accepts an instance of `Illuminate\Htpp\Request` so it can be used for filtration like this:

```php
public function index(Request $request)
{
    return Product::filter($request)->get();
}
```
Once you have added the trait to your model, and used the `filter` method , you just need to start creating filters.

### Creating filters

A filter class is a class that extends `Xbhub\Filter\Filter`.

You can manually create a filter class and place wherever you like, or use the `make:filter` artisan command to create a filter which will be placed inside  `app/Http/Filters` directory.

```bash
php artisan make:filter Product/ColorFilter
```
This will typically create the following class:

```php
<?php

namespace App\Http\Filters\Product;

use Xbhub\Filter\Filter;
use Illuminate\Database\Eloquent\Builder;

class ColorFilter extends Filter
{
    /**
     * Filter values mappings.
     *
     * @var array
     */
    protected $mappings = [
        //
    ];

    /**
     * Filter records.
     *
     * @param Builder $builder
     * @param mixed   $value
     *
     * @return Builder
     */
    public function filter(Builder $builder, $value)
    {
        //
    }
}
```
The `filter` method inside a filter class, is where you should put your filtration logic.
The `$value` parameter is holding the value passed in the query string for this specific filter key.

```php
/**
 * Filter records.
 *
 * @param Builder $builder
 * @param mixed   $value
 *
 * @return Builder
 */
public function filter(Builder $builder, $value)
{
    // Assuming the URL is https://example.com/products?color=red
    // $value here is equal to 'red'

    return $builder->where('color', $value);
}
```
> Note: the key 'color' in the above example is defined when you actually [use the filter](#using-individual-filters)

Because you have an instance of `Illuminate\Database\Eloquent\Builder` you have all of its power, which means you can do all different sorts of things:

* Ordering
```php
public function filter(Builder $builder, $value)
{
    return $builder->orderBy('price', $value);
}
```
* Filtering by relations
```php
public function filter(Builder $builder, $value)
{
    return $builder->whereHas('category', function($query) use ($value){
        return $query->where('name', $value);
    });
}
```

### Mappings
Sometimes you might want to use more meaningful values for your query string keys, and these values could be different from the values you actually need for filtration, this is where the `$mappings` array role comes.

You can define your values mappings in the `$mappings` array , and then resolve the values back using the `resolveValue` method inside your filter class.

The `resolveValue` method resolves the value out of the `$mappings` array or returns `null` if the value is not found.
> Pay attention to the `null` because it can sometimes cause unexpected behavior when filtering or ordering your records.

Example:
Let's assume you would like to order a list of products by their price, and you don't want to have:
`..?price=asc` or `..?price=desc` in your URL,
instead you would like to have something like:
`..?price=lowest` or `..?price=highest`

to achieve this you have the following filter class:
```php
<?php

namespace App\Http\Filters\Product;

use Xbhub\Filter\Filter;
use Illuminate\Database\Eloquent\Builder;

class ColorFilter extends Filter
{
    /**
     * Filter values mappings.
     *
     * @var array
     */
    protected $mappings = [
        'lowest' => 'asc',
        'highest' => 'desc',
    ];

    /**
     * Filter records.
     *
     * @param Builder $builder
     * @param mixed   $value
     *
     * @return Builder
     */
    public function filter(Builder $builder, $value)
    {
        // Assume $value is 'lowest'
        $value = $this->resolveValue($value);
        // $value now is 'asc'

        // URL doesn't have neither 'asc' nor 'desc'
        if ($value === null) {
            return $builder // Don't do anything
        }

        return $builder->orderBy('price', $value);
    }
}
```


### Using "individual" filters

Once you have created your filter and defined your filtration logic, It's time now to actually use the filter, which can be done in three ways:
* [Passing a filters array](#passing-a-filters-array-to-filter) as a second parameter to the `filter` scope.
* [Defining model filters](#defining-model-filters) inside the model itself.
* Using [filter bags](#filter-bags).

#### Passing a filters array to `filter`:
Use this when you want to apply a filter to a single query:

```php
public function index(Request $request)
{
    return Product::filter($request,[
        // "color" here is the key to be used in the query string
        // e.g. https://example.com/products?color=red
        "color" => \App\Http\Filters\Product\ColorFilter::class,
    ])->get();
}
```
In the above example the filter will look for the value of the key *color* in the query string for this query only, and pass it to the `filter` method inside the filter class where you have put your filtration logic.

#### Defining model filters:
The `Filterable` trait providers a `filters` method that should return an array of "keys" and "filters" to be applied to the model every time the `filter` method is called.

```php
<?php

namespace App;

use Xbhub\Filter\Concerns\Filterable;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Filterable;

    protected $fieldSearchable = [
        'name' => 'like',
        'id' => '=',
        'email' => 'like'
    ];

    /**
     * List of individual filters to be used by the model.
     *
     * @return array
     */
    protected function filters()
    {
        return [
            'color' => \App\Http\Filters\Product\ColorFilter::class,
        ];
    }
}
```
Now everytime you call the `filter` method on the model, you will have the `ColorFilter` applied on your query without passing any external arguments.

```php
public function index(Request $request)
{
    // The ColorFilter is automatically applied.
    return Product::filter($request)->get();
}
```


### Filter Bags
Sometimes you might have a large list of filters that is making your model look messy, or you might want to group a list of filters so you can share them between different models. This is when you want to have a filter bag.

A filter bag is a class that extends `Xbhub\Filter\FilterBag` and contains an array of filters that are always applied together.

Again you can manually create your filter bag class and place it wherever you like, or use the `make:filter-bag` command to create a filter bag class that is placed inside the `app\Http\Filters` directory.

```bash
php artisan make:filter-bag Product/ProductFilters
```
This will typically create the following class:

```php
<?php

namespace App\Http\Filters\Product;

use Xbhub\Filter\FilterBag;

class ProductFilters extends FilterBag
{
    /**
     * Filters to be applied.
     *
     * @var array
     */
    protected static $filters = [
        //
    ];
}
```

Again, put your "key" and "filter" pairs inside the `$filters` array:
```php
/**
 * Filters to be applied.
 *
 * @var array
 */
protected static $filters = [
    'color' => \App\Http\Filters\Product\ColorFilter::class,
    'condition' => \App\Http\Filters\Product\UsedFilter::class,
    'q' => \App\Http\Filters\Product\NameFilter::class,
    't' => \App\Http\Filters\TrashedFilter::class,
];
```
And once you have done that, you just need to override the `filterBag` method from the `Filterable` trait inside your model, to return which filter bag should be used.
```php
<?php

namespace App;

use Xbhub\Filter\Concerns\Filterable;
use App\Http\Filters\Product\ProductFilters;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use Filterable;

    /**
     * Filter bag used by the model.
     *
     * @return string
     */
    protected function filterBags()
    {
        return [
            ProductFilters::class,
        ];
    }
}
```
Now everytime the `filter` method is called on the model, all the filters inside the `ProductFilters`  will be applied.

```php
public function index(Request $request)
{
    // The filters from ProductFilters are automatically applied.
    return Product::filter($request)->get();
}
```

## Default-filters

```php

/users?search=name:John;email:john@gmail.com
/users?search=age:17;email:john@gmail.com&searchJoin=and
/users?filter=id;name
/users?filter=id;name&orderBy=id&sortedBy=desc
/users?orderBy=posts|title&sortedBy=desc
/users?with=groups
/users?withCount=groups

```

## Inspired

- [aldemeery/sieve](https://github.com/aldemeery/sieve)
- [andersao/l5-repository](https://github.com/andersao/l5-repository)

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
