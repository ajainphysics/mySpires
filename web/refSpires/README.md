# RefSpires

## Basic Usage

Include this in your `<head>`.

```html
<script src="/path/to/refSpires.js"></script>
<script>
  RefSpires.load();
</script>
```

Create a reference list as:

```html
<ul class="inspireList" data-query="..." data-opts="..."></ul>
```

The attribute `data-query` is required and must be the query you want to search on insprirehep.net. For example: `exactauthor:A.Jain.5`, `find a jain, akash` or `find eprint 1602.07982`. The attribute `data-opts` is optional and must be a JSON object containing options.

## Options

Options can be set for each list individually as a JSON object in `data-opts` attribute, or globally by setting `RefSpires.opts` before calling `RefSpires.load()`. Available options are:

- `(string) boldAuthor`: The name of the author formatted as `Firstname Middlename LastName` which should be bolded. Must exactly match the corresponding authors in references.
- `(string) itemClass`: A class selector to apply to all `<li>` items.
- `(string) itemIdPrefix`: An id prefix for `<li>` items. Items will get sequential ids `prefix-0`, `prefix-1` and so on.

A sample configuration would be:
```
RefSpires.opts = {
  boldAuthor: "Akash Jain",
  itemClass: "list-items",
  itemIdPrefix: "item"
}
```

Similarly:
```
<ul class="inspireList" 
    data-query="..." 
    data-opts="{"boldAuthor": "Akash Jain", "itemClass": "list-items", "itemIdPrefix": "item"}"></ul>
```
