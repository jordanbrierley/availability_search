<div class="sample-container">test

	{{ if items_exist == false }}
		<p>There are no items.</p>
	{{ else }}
		<div class="sample-data">
			<table cellpadding="0" cellspacing="0">
				<tr>
					<th>{{ helper:lang line="events:name" }}</th>
					<th>{{ helper:lang line="events:slug" }}</th>
				</tr>
				<!-- Here we loop through the $items array -->
				{{ entries }}
				<tr>
					<td>{{ name }}</td>
					<td>{{ slug }}</td>
				</tr>
				{{ /entries }}
			</table>
		</div>
	
		{{ pagination:links }}
	
	{{ endif }}
	
</div>