// Inicialização da BD por plataforma (import condicional):
// - Web: stub (no-op) — não importa sqflite FFI.
// - Nativo (Windows/desktop/mobile): usa db_init_ffi.
export 'db_init_stub.dart' if (dart.library.io) 'db_init_ffi.dart';
