# CollectionConverter
A Converter Class that Exports Eloquent Models to CSV, TSV, PSV, and XML

example usage:
$models  = Models::all();                                                           
$CC      = CollectionConverter::makeFromPreset(CollectionConverter::CSV, "testing");
-or-                                                                                
$CC      = CollectionConverter::makeFromPreset(CollectionConverter::XML, "testing");
$CC->exportFormattedModelCollection($models);                                       


Requirements:
______________________________
PHP     >= 5.4
Laravel >= 4.2
