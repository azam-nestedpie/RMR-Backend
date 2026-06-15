<h1>Dashboard Report</h1>

<p>Your dashboard report is ready.</p>

<h2>Summary</h2>
<ul>
    <li>Average team rating: {{ $dashboardData['summary']['avg_team_rating'] ?? 0 }}</li>
    <li>Trend: {{ $dashboardData['summary']['trend']['value'] ?? 0 }}</li>
    <li>Current month ratings: {{ $dashboardData['summary']['ratings_count']['current_month'] ?? 0 }}</li>
    <li>Last month ratings: {{ $dashboardData['summary']['ratings_count']['last_month'] ?? 0 }}</li>
</ul>

<h2>Report Data</h2>
<pre>{{ json_encode($dashboardData, JSON_PRETTY_PRINT) }}</pre>
