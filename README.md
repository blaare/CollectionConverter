# CollectionConverter
A Converter Class that Exports Eloquent Models to CSV, TSV, PSV, and XML

## Example
```php
$models  = Models::all();                                                           
$CC      = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing");
$CC->exportFormattedModelCollection($models);                                       
```
## Requirements
PHP     >= 5.4
Laravel >= 4.2

#### Created By
Timothy Wilson
