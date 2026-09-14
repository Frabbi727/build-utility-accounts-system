import 'dart:io';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';
import '../constants/api_endpoints.dart';
import '../network/api_client.dart';

/// Top-level background message handler for Firebase Messaging
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}

  debugPrint('FCM Background message received: ${message.messageId} - ${message.notification?.title}');
}

class PushNotificationService {
  static final PushNotificationService _instance = PushNotificationService._internal();
  factory PushNotificationService() => _instance;
  PushNotificationService._internal();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();
  final Uuid _uuid = const Uuid();

  static const String _deviceIdKey = 'uas_resident_device_id';
  static const AndroidNotificationChannel _defaultChannel = AndroidNotificationChannel(
    'uas_high_importance_channel',
    'UAS Notifications',
    description: 'Important notifications regarding bills, payments, maintenance, and notices.',
    importance: Importance.high,
    playSound: true,
  );

  bool _isInitialized = false;
  String? _cachedDeviceId;

  /// Initialize Firebase Messaging and local notifications plugin
  Future<void> initialize({
    void Function(Map<String, dynamic> data)? onNotificationOpened,
  }) async {
    if (_isInitialized) return;

    try {
      // 1. Request notification permissions
      final settings = await _messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
      );

      debugPrint('FCM Permission authorization status: ${settings.authorizationStatus}');

      // 2. Setup Android notification channels & local notifications
      const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
      const iosInit = DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true,
      );
      const initSettings = InitializationSettings(android: androidInit, iOS: iosInit);

      await _localNotifications.initialize(
        initSettings,
        onDidReceiveNotificationResponse: (NotificationResponse response) {
          if (response.payload != null && onNotificationOpened != null) {
            try {
              // Parse simple key-value pairs or dispatch callback
              onNotificationOpened({'payload': response.payload});
            } catch (e) {
              debugPrint('Error parsing notification payload: $e');
            }
          }
        },
      );

      // Create Android Notification Channel
      final androidPlugin = _localNotifications
          .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      if (androidPlugin != null) {
        await androidPlugin.createNotificationChannel(_defaultChannel);
      }

      // 3. Foreground notification presentation options on iOS
      await _messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      // 4. Foreground message listener
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        debugPrint('FCM Foreground message: ${message.notification?.title} - ${message.notification?.body}');
        _showLocalNotification(message);
      });

      // 5. App opened from background notification
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        debugPrint('FCM Notification opened from background: ${message.data}');
        if (onNotificationOpened != null) {
          onNotificationOpened(message.data);
        }
      });

      // 6. Check if app was opened from a terminated state notification
      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null && onNotificationOpened != null) {
        onNotificationOpened(initialMessage.data);
      }

      _isInitialized = true;
    } catch (e) {
      debugPrint('PushNotificationService initialization error: $e');
    }
  }

  /// Show local notification when app is in foreground
  Future<void> _showLocalNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;

    final androidDetails = AndroidNotificationDetails(
      _defaultChannel.id,
      _defaultChannel.name,
      channelDescription: _defaultChannel.description,
      importance: Importance.high,
      priority: Priority.high,
      icon: '@mipmap/ic_launcher',
    );

    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    final platformDetails = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );

    await _localNotifications.show(
      message.hashCode,
      notification.title,
      notification.body,
      platformDetails,
      payload: message.data.toString(),
    );
  }

  /// Get or create unique persistent device UUID
  Future<String> getDeviceId() async {
    if (_cachedDeviceId != null) return _cachedDeviceId!;

    var deviceId = await _secureStorage.read(key: _deviceIdKey);
    if (deviceId == null || deviceId.isEmpty) {
      deviceId = _uuid.v4();
      await _secureStorage.write(key: _deviceIdKey, value: deviceId);
    }
    _cachedDeviceId = deviceId;
    return deviceId;
  }

  /// Register FCM token and device details with backend server
  Future<bool> registerDevice(ApiClient apiClient) async {
    try {
      final token = await _messaging.getToken();
      if (token == null || token.isEmpty) {
        debugPrint('FCM token is null or unavailable');
        return false;
      }

      final deviceId = await getDeviceId();
      final platform = Platform.isAndroid ? 'android' : (Platform.isIOS ? 'ios' : 'web');

      final response = await apiClient.post(
        ApiEndpoints.devicesRegister,
        data: {
          'device_id': deviceId,
          'device_token': token,
          'platform': platform,
          'device_model': Platform.operatingSystem,
          'os_version': Platform.operatingSystemVersion,
          'app_version': '1.0.0',
        },
      );

      // Listen for token refreshes and re-register
      _messaging.onTokenRefresh.listen((newToken) async {
        try {
          await apiClient.post(
            ApiEndpoints.devicesRegister,
            data: {
              'device_id': deviceId,
              'device_token': newToken,
              'platform': platform,
            },
          );
        } catch (_) {}
      });

      debugPrint('Device registered with FCM token: $token');
      return response.success;
    } catch (e) {
      debugPrint('Failed to register device token with backend: $e');
      return false;
    }
  }

  /// Unregister/deactivate device on logout
  Future<void> unregisterDevice(ApiClient apiClient) async {
    try {
      final deviceId = await getDeviceId();
      await apiClient.delete(ApiEndpoints.deviceDelete(deviceId));
      debugPrint('Device unregistered from push notifications: $deviceId');
    } catch (e) {
      debugPrint('Failed to unregister device: $e');
    }
  }
}
