# 3F Translator
A simple Laravel package help you automatically translate all text on your site to other languages. Writed by Trieu Tai Niem.

##Features
* Automatically find translate string in parameter of translation functions ( *_()* or *lang()* ) and generate into json translation files

* Automatically translates text found into other languages ​​using google translator

* Automatically updates and translates newly added text


##Installation
Open terminal and change directory to your project folder, now using composer command bellow to install package:

```
composer require thienson98/tftranslator
```

Finally, open laravel config file *config/app.php* and add the following line to end of *$provider* array:

```
ThienSon98\TFTranslator\TFTranslatorServiceProvider::class
```

That's all!

##How to use?

If you want to automatically generate locks from translation functions into json language files, use the following command:

```
php artisan 3F:translator
```

Of course, you can also use the above command to update the changes in your files

### Auto find and insert translation function
You do not want to take the effort to insert text as parameter of translation function?

Do not worry! You just execute the command bellow:

```
php artisan 3F:translator --auto
```

Translation functions will be added to the view files and automatically generated json language files.


###Specify other languages

The default language of the translator is *Vietnamese (vi)* and *English (en)*. You can translate into other languages ​​through the ```--lang=<language code>``` option.

For example, use the following command to translate into Japanese:

```
php artisan 3F:translator --lang=ja
```

Or translate into Vietnamese, Japanese and Chinese:

```
php artisan 3F:translator --lang=ja,vi,zh
```

It supports all languages.

###Remove unused keys

To clean unused translation keys, you can use the following command:

```
php artisan 3F:translator --clear
```

It will remove all the keys that are not using in views.

###Just write the translation keys
Of course, if you do not want to use Google translator to translate your texts. You can use the option below:

```
php artisan 3F:translator --justwrite
```

It will ignore auto-translations and only give you translation keys into json files.

This option works with all of the options above.