# Atrium custom fields

Fields that editors add to any [Atrium](https://github.com/AlveBy/atrium) module from the admin, with no migration and no code: a colour and sizes for products, a season for tours, a subtitle for news. They show on the module's form (on the tab you choose), in its list, in the API and in the MCP tools, and the filterable ones drive a shop's filter panel with facet counts, fast on 100 000 records in MySQL, PostgreSQL and SQLite.

## Install

```bash
composer require alveby/atrium-custom-fields
php artisan atrium:module:install alveby/atrium-custom-fields
```

Every module's list gets a **Fields** button. A field has a type (line, text, integer, decimal, yes/no, date, list, multiple choice, link to a record, file, image), a name and a hint in every content language, a tab, and the flags required, show in the list, filterable, sortable, searchable, translatable and per site. The first field of a module adds a JSON column `custom` to its table.

With `--demo` the catalog's demo products get five filterable fields (weight, battery life, waterproof, connectivity, warranty) in the shop's main language, in pounds for English and kilograms for every other language.

## In code

```php
$product->custom('color');                     // with the HasCustomFields trait
app(CustomFields::class)->get($product, 'color');

Product::query()
    ->whereCustomFilters(['color' => ['red', 'blue'], 'weight' => ['max' => 5]])
    ->orderByCustom('rating', 'desc')
    ->paginate(24);

CustomFacets::for(Product::class)->filters($filters)->counts(['color', 'sizes']);
```

Filterable and sortable values are copied into a typed index table on every save, so a filter is an indexed lookup and a facet one `GROUP BY`. `atrium:custom-fields:reindex` rebuilds the index, `atrium:custom-fields:promote` moves a hot field into a real indexed column.

The guide, with the speed figures: [docs/custom-fields.md](https://github.com/AlveBy/atrium/blob/main/docs/custom-fields.md) ([по-русски](https://github.com/AlveBy/atrium/blob/main/docs/custom-fields.ru.md)).

## Tests

From the repository root:

```bash
vendor/bin/phpunit packages/custom-fields/tests
```

The tests cover the field screen, the form, the list, the API and MCP, translations and sites, filters, sorting, facets, the index and promotion, on SQLite, MySQL and PostgreSQL. `CUSTOM_FIELDS_BENCHMARK=100000` adds the benchmark.

## Contributing

This repository is a read-only copy of `packages/custom-fields` in [AlveBy/atrium](https://github.com/AlveBy/atrium). Issues and pull requests go there.

## License

MIT
