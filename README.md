Introduction
============

This bundle is used to load fixtures in Yaml with Symfony 2

Installation
------------

  1. Add this bundle to your ``vendor/`` dir:
      * Using the vendors script.

        Add the following lines in your ``deps`` file::

            [SnowcapYamlFixturesBundle]
                git=git://github.com/snowcap/SnowcapYamlFixturesBundle.git
                target=/bundles/Snowcap/YamlFixturesBundle
            
        Run the vendors script:

            ./bin/vendors install

      * Using git submodules.

            $ git submodule add git://github.com/Snowcap/SnowcapYamlFixturesBundle.git vendor/bundles/Snowcap/YamlFixturesBundle

  2. Add the Snowcap namespace to your autoloader:

          // app/autoload.php
          $loader->registerNamespaces(array(
                'Snowcap' => __DIR__.'/../vendor/bundles',
                // your other namespaces
          ));

  3. Add this bundle to your application's kernel:

          // app/ApplicationKernel.php
          public function registerBundles()
          {
              return array(
                  // ...
                  new Snowcap\YamlFixturesBundle\SnowcapYamlFixturesBundle(),
                  // ...
              );
          }
          
 