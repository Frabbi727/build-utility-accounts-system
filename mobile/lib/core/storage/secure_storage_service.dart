import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SecureStorageService {
  final FlutterSecureStorage _secureStorage;
  SharedPreferences? _prefs;

  static const String _authTokenKey = 'uas_resident_token';
  static const String _selectedFlatKey = 'uas_selected_flat_id';
  static const String _userCacheKey = 'uas_cached_user';

  SecureStorageService({
    FlutterSecureStorage? secureStorage,
  }) : _secureStorage = secureStorage ?? const FlutterSecureStorage();

  Future<void> init() async {
    _prefs ??= await SharedPreferences.getInstance();
  }

  Future<void> saveToken(String token) async {
    await _secureStorage.write(key: _authTokenKey, value: token);
  }

  Future<String?> getToken() async {
    return await _secureStorage.read(key: _authTokenKey);
  }

  Future<void> deleteToken() async {
    await _secureStorage.delete(key: _authTokenKey);
  }

  Future<void> saveSelectedFlatId(int flatId) async {
    await _prefs?.setInt(_selectedFlatKey, flatId);
  }

  int? getSelectedFlatId() {
    return _prefs?.getInt(_selectedFlatKey);
  }

  Future<void> saveUserCache(String userJson) async {
    await _prefs?.setString(_userCacheKey, userJson);
  }

  String? getUserCache() {
    return _prefs?.getString(_userCacheKey);
  }

  Future<void> clearAll() async {
    await _secureStorage.deleteAll();
    await _prefs?.clear();
  }
}
