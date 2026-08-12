import 'dart:io';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

/// Em Windows/Linux/macOS, o sqflite precisa do factory FFI.
/// Em Android/iOS é no-op (usa o factory nativo por omissão).
void initDesktopDb() {
  if (Platform.isWindows || Platform.isLinux || Platform.isMacOS) {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  }
}
