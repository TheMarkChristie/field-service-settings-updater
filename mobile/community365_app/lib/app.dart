import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'services/settings_service.dart';
import 'screens/home_screen.dart';
import 'theme.dart';

class Community365App extends StatelessWidget {
  const Community365App({super.key});

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<SettingsService>();
    return MaterialApp(
      title: '365 Community',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: settings.themeMode,
      home: const HomeScreen(),
    );
  }
}
