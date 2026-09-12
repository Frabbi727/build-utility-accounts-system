import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/confirm_dialog.dart';
import '../bloc/maintenance_bloc.dart';
import '../bloc/maintenance_event.dart';
import '../bloc/maintenance_state.dart';

class CreateTicketScreen extends StatefulWidget {
  const CreateTicketScreen({super.key});

  @override
  State<CreateTicketScreen> createState() => _CreateTicketScreenState();
}

class _CreateTicketScreenState extends State<CreateTicketScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descController = TextEditingController();

  String _category = 'Plumbing';
  String _priority = 'medium';

  @override
  void dispose() {
    _titleController.dispose();
    _descController.dispose();
    super.dispose();
  }

  void _confirmAndSubmit(BuildContext context) async {
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();

    final storage = context.read<SecureStorageService>();
    final flatId = storage.getSelectedFlatId();
    if (flatId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No active flat selected.'),
          backgroundColor: AppTheme.error,
        ),
      );
      return;
    }

    final confirmed = await ConfirmDialog.show(
      context,
      title: 'Submit Maintenance Request',
      message: 'Are you sure you want to log this support request? Building staff will be notified.',
      details: {
        'Category': _category,
        'Priority': _priority.toUpperCase(),
        'Title': _titleController.text.trim(),
      },
      confirmText: 'Submit Request',
      cancelText: 'Cancel',
    );

    if (confirmed == true && mounted) {
      context.read<MaintenanceBloc>().add(
            MaintenanceCreateRequested(
              flatId: flatId,
              category: _category,
              title: _titleController.text.trim(),
              description: _descController.text.trim(),
              priority: _priority,
            ),
          );
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => MaintenanceBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      ),
      child: Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          title: const Text('Log Maintenance Issue'),
        ),
        body: SafeArea(
          bottom: true,
          child: BlocConsumer<MaintenanceBloc, MaintenanceState>(
            listener: (context, state) {
              if (state is MaintenanceCreateSuccess) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Support request submitted successfully!'),
                    backgroundColor: AppTheme.success,
                  ),
                );
                Navigator.of(context).pop(true);
              } else if (state is MaintenanceError) {
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
              final isSubmitting = state is MaintenanceSubmitting;

              return Column(
                children: [
                  Expanded(
                    child: SingleChildScrollView(
                      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                      padding: const EdgeInsets.all(20),
                      child: Form(
                        key: _formKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Category Dropdown
                            const Text('Issue Category', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            DropdownButtonFormField<String>(
                              initialValue: _category,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.category_outlined, color: AppTheme.textSecondary),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'Plumbing', child: Text('Plumbing / Water Supply')),
                                DropdownMenuItem(value: 'Electrical', child: Text('Electrical / Wiring')),
                                DropdownMenuItem(value: 'Elevator', child: Text('Elevator / Lift')),
                                DropdownMenuItem(value: 'Carpentry', child: Text('Carpentry / Doors')),
                                DropdownMenuItem(value: 'Security', child: Text('Security / Access')),
                                DropdownMenuItem(value: 'Cleaning', child: Text('Waste / Cleaning')),
                                DropdownMenuItem(value: 'General', child: Text('General Maintenance')),
                              ],
                              onChanged: isSubmitting
                                  ? null
                                  : (val) {
                                      if (val != null) setState(() => _category = val);
                                    },
                            ),
                            const SizedBox(height: 18),

                            // Priority Dropdown
                            const Text('Priority Level', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            DropdownButtonFormField<String>(
                              initialValue: _priority,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.flag_outlined, color: AppTheme.textSecondary),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'low', child: Text('Low (Can wait few days)')),
                                DropdownMenuItem(value: 'medium', child: Text('Medium (Normal attention)')),
                                DropdownMenuItem(value: 'high', child: Text('High (Urgent within 24h)')),
                                DropdownMenuItem(value: 'urgent', child: Text('Urgent (Immediate safety / leak)')),
                              ],
                              onChanged: isSubmitting
                                  ? null
                                  : (val) {
                                      if (val != null) setState(() => _priority = val);
                                    },
                            ),
                            const SizedBox(height: 18),

                            // Issue Title
                            const Text('Issue Title', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: _titleController,
                              textInputAction: TextInputAction.next,
                              enabled: !isSubmitting,
                              decoration: const InputDecoration(
                                hintText: 'e.g. Master bathroom tap leaking continuously',
                                prefixIcon: Icon(Icons.title_rounded, color: AppTheme.textSecondary),
                              ),
                              validator: (val) {
                                if (val == null || val.trim().isEmpty) return 'Please enter an issue title';
                                if (val.trim().length < 5) return 'Title must be at least 5 characters';
                                return null;
                              },
                            ),
                            const SizedBox(height: 18),

                            // Description
                            const Text('Detailed Description', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: _descController,
                              textInputAction: TextInputAction.done,
                              enabled: !isSubmitting,
                              maxLines: 5,
                              decoration: const InputDecoration(
                                hintText: 'Please provide exact details or location inside the apartment...',
                              ),
                              validator: (val) {
                                if (val == null || val.trim().isEmpty) return 'Please enter description details';
                                return null;
                              },
                            ),
                            const SizedBox(height: 24),
                          ],
                        ),
                      ),
                    ),
                  ),

                  // Fixed bottom action bar with safe area
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                    decoration: BoxDecoration(
                      color: AppTheme.surface,
                      border: const Border(top: BorderSide(color: AppTheme.border)),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.04),
                          blurRadius: 8,
                          offset: const Offset(0, -2),
                        ),
                      ],
                    ),
                    child: AppButton(
                      text: 'Submit Support Ticket',
                      isLoading: isSubmitting,
                      icon: Icons.send_rounded,
                      onPressed: () => _confirmAndSubmit(context),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }
}
