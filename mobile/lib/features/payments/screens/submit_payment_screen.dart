import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/app_button.dart';
import '../../../core/widgets/confirm_dialog.dart';
import '../bloc/payment_submission_bloc.dart';
import '../bloc/payment_submission_event.dart';
import '../bloc/payment_submission_state.dart';

class SubmitPaymentScreen extends StatefulWidget {
  final String? prefilledAmount;
  final String? prefilledBillNo;

  const SubmitPaymentScreen({
    super.key,
    this.prefilledAmount,
    this.prefilledBillNo,
  });

  @override
  State<SubmitPaymentScreen> createState() => _SubmitPaymentScreenState();
}

class _SubmitPaymentScreenState extends State<SubmitPaymentScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _amountController;
  late final TextEditingController _referenceController;
  late final TextEditingController _notesController;

  String _paymentMethod = 'bKash';
  File? _slipFile;
  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    _amountController = TextEditingController(text: widget.prefilledAmount ?? '');
    _referenceController = TextEditingController();
    _notesController = TextEditingController(
      text: widget.prefilledBillNo != null ? 'Payment for Bill #${widget.prefilledBillNo}' : '',
    );
  }

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(source: source, imageQuality: 85);
      if (picked != null) {
        setState(() {
          _slipFile = File(picked.path);
        });
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not access camera/gallery.'),
            backgroundColor: AppTheme.error,
          ),
        );
      }
    }
  }

  void _confirmAndSubmit(BuildContext context) async {
    if (!_formKey.currentState!.validate()) return;

    FocusScope.of(context).unfocus();

    final storage = context.read<SecureStorageService>();
    final flatId = storage.getSelectedFlatId();
    if (flatId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No active flat selected. Please select a flat on the dashboard.'),
          backgroundColor: AppTheme.error,
        ),
      );
      return;
    }

    final amount = _amountController.text.trim();
    final reference = _referenceController.text.trim();
    final method = _paymentMethod;

    // Contextual Confirmation Dialog
    final confirmed = await ConfirmDialog.show(
      context,
      title: 'Confirm Payment Submission',
      message:
          'Please verify your transaction details. Submissions enter the verification queue for property staff approval.',
      details: {
        'Amount': '৳ $amount',
        'Payment Method': method,
        'TrxID / Ref No': reference,
        'Attachment': _slipFile != null ? 'Slip attached' : 'No attachment',
      },
      confirmText: 'Submit Now',
      cancelText: 'Cancel',
    );

    if (confirmed == true && mounted) {
      context.read<PaymentSubmissionBloc>().add(
            PaymentSubmitRequested(
              flatId: flatId,
              amount: amount,
              paymentMethod: method,
              referenceNumber: reference,
              slipFile: _slipFile,
              notes: _notesController.text.trim(),
            ),
          );
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => PaymentSubmissionBloc(
        apiClient: context.read<ApiClient>(),
        storageService: context.read<SecureStorageService>(),
      ),
      child: Scaffold(
        backgroundColor: AppTheme.background,
        appBar: AppBar(
          title: const Text('Submit Payment'),
        ),
        body: SafeArea(
          bottom: true,
          child: BlocConsumer<PaymentSubmissionBloc, PaymentSubmissionState>(
            listener: (context, state) {
              if (state is PaymentSubmitSuccess) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Payment slip submitted successfully for verification.'),
                    backgroundColor: AppTheme.success,
                  ),
                );
                Navigator.of(context).pop(true);
              } else if (state is PaymentSubmissionError) {
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
              final isSubmitting = state is PaymentSubmitting;

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
                            // Info Card
                            Container(
                              padding: const EdgeInsets.all(14),
                              decoration: BoxDecoration(
                                color: AppTheme.primaryLight.withValues(alpha: 0.5),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: AppTheme.primary.withValues(alpha: 0.3)),
                              ),
                              child: const Row(
                                children: [
                                  Icon(Icons.info_outline_rounded, color: AppTheme.primary, size: 20),
                                  SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      'Pay via your preferred mobile banking or bank deposit, then submit your transaction details below.',
                                      style: TextStyle(fontSize: 13, color: AppTheme.primaryDark),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 20),

                            // Payment Method
                            const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            DropdownButtonFormField<String>(
                              initialValue: _paymentMethod,
                              decoration: const InputDecoration(
                                prefixIcon: Icon(Icons.account_balance_wallet_outlined, color: AppTheme.textSecondary),
                              ),
                              items: const [
                                DropdownMenuItem(value: 'bKash', child: Text('bKash Merchant / Personal')),
                                DropdownMenuItem(value: 'Nagad', child: Text('Nagad')),
                                DropdownMenuItem(value: 'Rocket', child: Text('Rocket')),
                                DropdownMenuItem(value: 'Bank Transfer', child: Text('Bank Transfer (EFT / NPSB)')),
                                DropdownMenuItem(value: 'Cash Deposit', child: Text('Cash Deposit to Management')),
                              ],
                              onChanged: isSubmitting
                                  ? null
                                  : (val) {
                                      if (val != null) setState(() => _paymentMethod = val);
                                    },
                            ),
                            const SizedBox(height: 18),

                            // Amount Field
                            const Text('Paid Amount (BDT)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: _amountController,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              textInputAction: TextInputAction.next,
                              enabled: !isSubmitting,
                              decoration: const InputDecoration(
                                prefixText: '৳ ',
                                prefixStyle: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: AppTheme.textPrimary),
                                hintText: '0.00',
                              ),
                              validator: (val) {
                                if (val == null || val.trim().isEmpty) return 'Please enter paid amount';
                                final parsed = double.tryParse(val.trim());
                                if (parsed == null || parsed <= 0) return 'Amount must be greater than 0';
                                return null;
                              },
                            ),
                            const SizedBox(height: 18),

                            // TrxID / Reference Number
                            const Text('Transaction ID / Slip Reference', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: _referenceController,
                              textInputAction: TextInputAction.next,
                              enabled: !isSubmitting,
                              decoration: const InputDecoration(
                                hintText: 'e.g. 9J4K82L10Q or Deposit Slip No',
                                prefixIcon: Icon(Icons.tag_rounded, color: AppTheme.textSecondary),
                              ),
                              validator: (val) {
                                if (val == null || val.trim().isEmpty) {
                                  return 'Please enter transaction ID or slip reference';
                                }
                                return null;
                              },
                            ),
                            const SizedBox(height: 18),

                            // Notes / Bill Reference
                            const Text('Notes (Optional)', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            TextFormField(
                              controller: _notesController,
                              textInputAction: TextInputAction.done,
                              enabled: !isSubmitting,
                              maxLines: 2,
                              decoration: const InputDecoration(
                                hintText: 'Add additional note or specify bill month...',
                              ),
                            ),
                            const SizedBox(height: 20),

                            // Slip Attachment Picker
                            const Text('Upload Payment Screenshot / Slip', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                            const SizedBox(height: 8),
                            if (_slipFile == null) ...[
                              Row(
                                children: [
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      style: OutlinedButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(vertical: 14),
                                        side: const BorderSide(color: AppTheme.border),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      ),
                                      icon: const Icon(Icons.camera_alt_outlined, color: AppTheme.primary),
                                      label: const Text('Camera', style: TextStyle(color: AppTheme.textPrimary)),
                                      onPressed: isSubmitting ? null : () => _pickImage(ImageSource.camera),
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  Expanded(
                                    child: OutlinedButton.icon(
                                      style: OutlinedButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(vertical: 14),
                                        side: const BorderSide(color: AppTheme.border),
                                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                      ),
                                      icon: const Icon(Icons.photo_library_outlined, color: AppTheme.primary),
                                      label: const Text('Gallery', style: TextStyle(color: AppTheme.textPrimary)),
                                      onPressed: isSubmitting ? null : () => _pickImage(ImageSource.gallery),
                                    ),
                                  ),
                                ],
                              ),
                            ] else ...[
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: AppTheme.surface,
                                  borderRadius: BorderRadius.circular(10),
                                  border: Border.all(color: AppTheme.border),
                                ),
                                child: Row(
                                  children: [
                                    ClipRRect(
                                      borderRadius: BorderRadius.circular(6),
                                      child: Image.file(
                                        _slipFile!,
                                        height: 50,
                                        width: 50,
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Text(
                                        _slipFile!.path.split('/').last,
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w500),
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.delete_outline_rounded, color: AppTheme.error),
                                      onPressed: isSubmitting
                                          ? null
                                          : () {
                                              setState(() {
                                                _slipFile = null;
                                              });
                                            },
                                    ),
                                  ],
                                ),
                              ),
                            ],
                            const SizedBox(height: 24),
                          ],
                        ),
                      ),
                    ),
                  ),

                  // Fixed Bottom Submit Action with Safe Area Insets
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
                      text: 'Submit Payment Slip',
                      isLoading: isSubmitting,
                      icon: Icons.upload_file_rounded,
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
