# sulu-block-grid

Grid and layout block collection for Sulu CMS — 8 CSS-grid-based layout blocks for flexible multi-column page structures.

## Included Blocks

| Block | Description |
|---|---|
| `block--grid-row` | Generic grid row container |
| `block--grid-row-1col` | Single-column row |
| `block--grid-row-2col` | Two-column row |
| `block--grid-row-3col` | Three-column row |
| `block--grid-col` | Individual grid column |
| `block--grid-three-col` | Three-column block with image cards |
| `block--grid-three-col-snippet` | Three-column block with snippets |
| `block--css-grid` | Flexible CSS grid block |

## Block Hierarchy

```
block--grid-row-2col / block--grid-row-3col
  └── block--grid-col

block--css-grid
  └── block--grid-col
```

## Requirements

- PHP 8.2+
- Symfony 7.0+
- Sulu CMS 3.0+
- `depa/sulu-block-helper`
- `depa/sulu-block-content` (referenced block types)

## Installation

```bash
composer require depa/sulu-block-grid
```

Register in `config/bundles.php`:

```php
Depa\SuluBlockHelperBundle\SuluBlockHelperBundle::class => ['all' => true],
Depa\SuluBlockGridBundle\SuluBlockGridBundle::class     => ['all' => true],
```

## License

Proprietary — Copyright (c) depa Berlin GmbH & Co. KG. All rights reserved.
See [LICENSE](LICENSE) for details.
