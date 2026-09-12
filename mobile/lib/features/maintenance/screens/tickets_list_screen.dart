import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../bloc/maintenance_bloc.dart';
import '../bloc/maintenance_event.dart';
import '../bloc/maintenance_state.dart';
import '../models/ticket_model.dart';
import 'create_ticket_screen.dart';

class TicketsListScreen extends StatelessWidget {
  const TicketsListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => MaintenanceBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      )..add(const MaintenanceFetchRequested()),
      child: const _TicketsListView(),
    );
  }
}

class _TicketsListView extends StatelessWidget {
  const _TicketsListView();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppTheme.background,
      appBar: AppBar(
        title: const Text('Maintenance & Support'),
      ),
      floatingActionButton: Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: FloatingActionButton.extended(
          backgroundColor: AppTheme.primary,
          foregroundColor: Colors.white,
          elevation: 3,
          icon: const Icon(Icons.add_rounded),
          label: const Text('New Request', style: TextStyle(fontWeight: FontWeight.w600)),
          onPressed: () async {
            final result = await Navigator.of(context).push(
              MaterialPageRoute(builder: (context) => const CreateTicketScreen()),
            );
            if (result == true && context.mounted) {
              context.read<MaintenanceBloc>().add(const MaintenanceFetchRequested());
            }
          },
        ),
      ),
      body: SafeArea(
        bottom: true,
        child: BlocConsumer<MaintenanceBloc, MaintenanceState>(
          listener: (context, state) {
            if (state is MaintenanceError) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text(state.message),
                  backgroundColor: AppTheme.error,
                  behavior: SnackBarBehavior.floating,
                ),
              );
            }
          },
          builder: (context, state) {
            if (state is MaintenanceLoading) {
              return const Center(child: CircularProgressIndicator(color: AppTheme.primary));
            }

            if (state is MaintenanceLoaded) {
              final tickets = state.tickets;
              if (tickets.isEmpty) {
                return RefreshIndicator(
                  color: AppTheme.primary,
                  onRefresh: () async {
                    context.read<MaintenanceBloc>().add(const MaintenanceFetchRequested());
                  },
                  child: ListView(
                    children: const [
                      SizedBox(height: 120),
                      Center(
                        child: Column(
                          children: [
                            Icon(Icons.build_circle_outlined, size: 56, color: AppTheme.textSecondary),
                            SizedBox(height: 12),
                            Text(
                              'No maintenance requests logged.',
                              style: TextStyle(color: AppTheme.textSecondary, fontSize: 15),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              }

              return RefreshIndicator(
                color: AppTheme.primary,
                onRefresh: () async {
                  context.read<MaintenanceBloc>().add(const MaintenanceFetchRequested());
                },
                child: ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 80), // Extra bottom padding for FAB
                  itemCount: tickets.length,
                  itemBuilder: (context, index) {
                    final ticket = tickets[index];
                    return _buildTicketCard(context, ticket);
                  },
                ),
              );
            }

            return const SizedBox.shrink();
          },
        ),
      ),
    );
  }

  Widget _buildTicketCard(BuildContext context, MaintenanceTicketModel ticket) {
    Color statusColor;
    Color statusBg;

    if (ticket.isResolved) {
      statusColor = AppTheme.success;
      statusBg = AppTheme.successLight;
    } else if (ticket.isInProgress) {
      statusColor = AppTheme.secondary;
      statusBg = const Color(0xFFE0F2FE);
    } else {
      statusColor = AppTheme.warning;
      statusBg = AppTheme.warningLight;
    }

    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: AppTheme.background,
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: AppTheme.border),
                  ),
                  child: Text(
                    ticket.category.toUpperCase(),
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.textSecondary),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusBg,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    ticket.status.replaceAll('_', ' ').toUpperCase(),
                    style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: statusColor),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            Text(
              ticket.title,
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
            ),
            const SizedBox(height: 6),
            Text(
              ticket.description,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: AppTheme.textSecondary, fontSize: 13, height: 1.3),
            ),
            const SizedBox(height: 12),
            const Divider(height: 1, color: AppTheme.divider),
            const SizedBox(height: 10),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(Icons.flag_outlined, size: 14, color: AppTheme.textSecondary),
                    const SizedBox(width: 4),
                    Text(
                      'Priority: ${ticket.priority.toUpperCase()}',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: ticket.priority == 'urgent' ? AppTheme.error : AppTheme.textSecondary,
                      ),
                    ),
                  ],
                ),
                if (ticket.assignedToName != null)
                  Text(
                    'Assigned: ${ticket.assignedToName}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.primary, fontWeight: FontWeight.w500),
                  )
                else
                  Text(
                    'Logged: ${ticket.createdAt.split('T').first}',
                    style: const TextStyle(fontSize: 11, color: AppTheme.textSecondary),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
