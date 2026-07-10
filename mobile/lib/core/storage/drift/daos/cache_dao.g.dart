// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'cache_dao.dart';

// ignore_for_file: type=lint
mixin _$CacheDaoMixin on DatabaseAccessor<AppDatabase> {
  $CacheEntriesTable get cacheEntries => attachedDatabase.cacheEntries;
  CacheDaoManager get managers => CacheDaoManager(this);
}

class CacheDaoManager {
  final _$CacheDaoMixin _db;
  CacheDaoManager(this._db);
  $$CacheEntriesTableTableManager get cacheEntries =>
      $$CacheEntriesTableTableManager(_db.attachedDatabase, _db.cacheEntries);
}
