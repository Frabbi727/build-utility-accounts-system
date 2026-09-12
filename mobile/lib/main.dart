import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_dotenv/flutter_dotenv.dart';
import 'core/network/api_client.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/bloc/auth_bloc.dart';
import 'features/auth/bloc/auth_event.dart';
import 'features/auth/bloc/auth_state.dart';
import 'features/auth/screens/login_screen.dart';
import 'features/auth/screens/profile_screen.dart';
import 'features/bills/screens/bills_list_screen.dart';
import 'features/dashboard/bloc/dashboard_bloc.dart';
import 'features/dashboard/bloc/dashboard_event.dart';
import 'features/dashboard/screens/resident_dashboard_screen.dart';
import 'features/maintenance/screens/tickets_list_screen.dart';
import 'features/notices/screens/notice_board_screen.dart';
import 'features/payments/screens/payment_history_screen.dart';
import 'features/payments/screens/submit_payment_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Load environment variables if .env exists
  try {
    await dotenv.load(fileName: '.env');
  } catch (_) {}

  final defaultBaseUrl = Platform.isAndroid
      ? 'http://10.0.2.2:8000/api/v1'
      : 'http://localhost:8000/api/v1';
  final baseUrl = dotenv.env['API_BASE_URL'] ?? defaultBaseUrl;

  final storageService = SecureStorageService();
  await storageService.init();

  final apiClient = ApiClient(baseUrl: baseUrl);

  runApp(
    MultiRepositoryProvider(
      providers: [
        RepositoryProvider.value(value: apiClient),
        RepositoryProvider.value(value: storageService),
      ],
      child: MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => AuthBloc(
              apiClient: apiClient,
              storageService: storageService,
            )..add(AuthCheckRequested()),
          ),
          BlocProvider(
            create: (context) => DashboardBloc(
              apiClient: apiClient,
              storageService: storageService,
            ),
          ),
        ],
        child: const ResidentApp(),
      ),
    ),
  );
}

class ResidentApp extends StatelessWidget {
  const ResidentApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'UAS Resident Portal',
      theme: AppTheme.lightTheme,
      debugShowCheckedModeBanner: false,
      home: BlocConsumer<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthAuthenticated) {
            context.read<DashboardBloc>().add(DashboardFetchRequested());
          }
        },
        builder: (context, state) {
          if (state is AuthInitial || state is AuthLoading) {
            return const Scaffold(
              body: Center(
                child: CircularProgressIndicator(color: AppTheme.primary),
              ),
            );
          }

          if (state is AuthAuthenticated) {
            return const MainNavigationScreen();
          }

          return const LoginScreen();
        },
      ),
    );
  }
}

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;

  void _onTabTapped(int index) {
    setState(() {
      _currentIndex = index;
    });
  }

  void _openSubmitPayment() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (context) => const SubmitPaymentScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      ResidentDashboardScreen(
        onNavigateToTab: _onTabTapped,
        onSubmitPayment: _openSubmitPayment,
      ),
      const BillsListScreen(),
      const PaymentHistoryScreen(),
      const TicketsListScreen(),
      const NoticeBoardScreen(),
      const ProfileScreen(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: SafeArea(
        bottom: true,
        child: Container(
          decoration: const BoxDecoration(
            border: Border(top: BorderSide(color: AppTheme.border, width: 1)),
          ),
          child: BottomNavigationBar(
            currentIndex: _currentIndex,
            onTap: _onTabTapped,
            items: const [
              BottomNavigationBarItem(
                icon: Icon(Icons.dashboard_outlined),
                activeIcon: Icon(Icons.dashboard_rounded),
                label: 'Home',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.receipt_long_outlined),
                activeIcon: Icon(Icons.receipt_long_rounded),
                label: 'Bills',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.payments_outlined),
                activeIcon: Icon(Icons.payments_rounded),
                label: 'Payments',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.build_circle_outlined),
                activeIcon: Icon(Icons.build_circle_rounded),
                label: 'Support',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.campaign_outlined),
                activeIcon: Icon(Icons.campaign_rounded),
                label: 'Notices',
              ),
              BottomNavigationBarItem(
                icon: Icon(Icons.person_outline_rounded),
                activeIcon: Icon(Icons.person_rounded),
                label: 'Profile',
              ),
            ],
          ),
        ),
      ),
    );
  }
}
