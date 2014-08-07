Data Access Layer
===

[![Latest Stable Version](https://poser.pugx.org/packaged/dal/version.png)](https://packagist.org/packages/packaged/dal)
[![Total Downloads](https://poser.pugx.org/packaged/dal/d/total.png)](https://packagist.org/packages/packaged/dal)
[![Build Status](https://travis-ci.org/packaged/dal.png)](https://travis-ci.org/packaged/dal)
[![Dependency Status](https://www.versioneye.com/php/packaged:dal/badge.png)](https://www.versioneye.com/php/packaged:dal)
[![HHVM Status](http://hhvm.h4cc.de/badge/packaged/dal.png)](http://hhvm.h4cc.de/package/packaged/dal)

Getting Started
===

    $resolver = new DalResolver($connectionConfigs,$datastoreConfigs);
    $resolver->boot();

FYI
===
DAO = Data Access Object

SQL Translation
====

**IDao** A Single Row within the database

**IDataStore** The Table within the database

**IDataConection** The connection to the database server e.g. PDO
